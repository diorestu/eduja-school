<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRequest;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Services\AbsenceRequestService;
use App\Services\AttendanceCommandService;
use App\Services\ConfiguredGpsAttendancePolicy;
use App\Services\IdentityResolver;
use App\Services\PortalMapService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PortalFoundationController extends Controller
{
    public function guru(SchoolContext $schoolContext): View
    {
        return $this->portal('Portal Guru & Wali Kelas', 'Dashboard kelas, presensi siswa, tunggakan wali kelas, dan approval izin.', [
            ['label' => 'Siswa Aktif', 'value' => Student::where('school_id', $schoolContext->activeSchoolId())->where('status', 'active')->count()],
            ['label' => 'Izin Pending', 'value' => AttendanceRequest::where('school_id', $schoolContext->activeSchoolId())->where('status', 'pending')->count()],
        ]);
    }

    public function siswa(SchoolContext $schoolContext, IdentityResolver $identityResolver, PortalMapService $maps): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $student = $identityResolver->studentFor(auth()->user(), $schoolId);

        if (! $student) {
            return view('pages.portal.empty-identity', ['title' => 'Portal Siswa', 'message' => 'Akun ini belum terhubung ke data siswa di sekolah aktif.', 'action' => 'Hubungi admin sekolah untuk menghubungkan akun siswa.']);
        }

        return view('pages.portal.siswa', $this->portalData($student, $schoolId) + [
            'title' => 'Portal Siswa',
            'map' => $maps->forSchool($schoolId),
        ]);
    }

    public function orangTua(SchoolContext $schoolContext, Request $request, PortalMapService $maps): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $students = Student::query()->active()->where('school_id', $schoolId)->where('guardian_user_id', $request->user()->id)->orderBy('name')->get();

        if ($students->isEmpty()) {
            return view('pages.portal.empty-identity', ['title' => 'Portal Orang Tua', 'message' => 'Belum ada data siswa yang terhubung ke akun ini.', 'action' => 'Minta admin sekolah menghubungkan data siswa ke akun wali murid.']);
        }

        $student = $students->firstWhere('id', $request->integer('student_id')) ?? $students->first();

        return view('pages.portal.orang-tua', $this->portalData($student, $schoolId) + [
            'title' => 'Portal Orang Tua',
            'students' => $students,
            'map' => $maps->forSchool($schoolId),
        ]);
    }

    public function storeAttendance(SchoolContext $schoolContext, Request $request, IdentityResolver $identityResolver, AttendanceCommandService $attendance): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor($request->user());
        $student = $identityResolver->studentFor($request->user(), $schoolId);
        if (! $student) {
            return back()->withErrors(['attendance' => 'Akun ini belum terhubung ke data siswa di sekolah aktif.']);
        }

        $data = $request->validate([
            'type' => ['required', 'in:masuk,pulang'],
            'location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required', 'numeric', 'between:-180,180'],
        ], ['location.latitude.required' => 'Lokasi perangkat diperlukan untuk presensi.', 'location.longitude.required' => 'Lokasi perangkat diperlukan untuk presensi.']);

        $classId = \App\Models\ClassStudent::query()->where('student_id', $student->id)
            ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $schoolId))
            ->value('school_class_id');
        if (! $classId) {
            return back()->withErrors(['attendance' => 'Kelas aktif siswa belum terhubung pada sekolah ini.']);
        }

        $gps = (new ConfiguredGpsAttendancePolicy(config('services.attendance.gps', [])))->evaluate(
            $schoolId,
            (float) $data['location']['latitude'],
            (float) $data['location']['longitude'],
        );
        if (! $gps['accepted']) {
            return back()->withErrors(['location' => $gps['reason']]);
        }

        $today = today()->toDateString();
        $existing = StudentAttendance::query()->where('school_id', $schoolId)->where('student_id', $student->id)->whereDate('attendance_date', $today)->first();
        if ($data['type'] === 'masuk' && $existing?->clock_in_at) {
            return back()->with('error', 'Presensi masuk sudah tercatat hari ini.');
        }
        if ($data['type'] === 'pulang' && ! $existing?->clock_in_at) {
            return back()->with('error', 'Presensi masuk perlu dicatat lebih dulu.');
        }
        if ($data['type'] === 'pulang' && $existing?->clock_out_at) {
            return back()->with('error', 'Presensi pulang sudah tercatat hari ini.');
        }

        $attributes = [
            'school_class_id' => $classId,
            'attendance_date' => $today,
            'status' => $existing?->status ?? 'H',
            'source' => 'gps',
            'latitude' => $gps['latitude'],
            'longitude' => $gps['longitude'],
        ];
        if ($data['type'] === 'masuk') {
            $attributes['clock_in_at'] = now()->toTimeString();
        } else {
            $attributes['clock_in_at'] = $existing->clock_in_at;
            $attributes['clock_out_at'] = now()->toTimeString();
        }
        $attendance->recordStudent($schoolId, $student->id, $attributes);

        return back()->with('success', $data['type'] === 'masuk' ? 'Presensi masuk berhasil dicatat.' : 'Presensi pulang berhasil dicatat.');
    }

    public function storePermission(SchoolContext $schoolContext, Request $request, AbsenceRequestService $absenceRequests): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor($request->user());
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'request_type' => ['required', 'in:izin,sakit,dispensasi'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $path = null;
        if ($file = $request->file('attachment')) {
            $path = $file->store('attendance-documents/'.$schoolId, 'local');
            $data['document_path'] = $path;
            $data['document_name'] = $file->getClientOriginalName();
            $data['document_mime'] = $file->getMimeType();
        }
        unset($data['attachment']);

        try {
            $absenceRequests->submitStudent($schoolId, $request->user(), ['subject_id' => $data['student_id'], ...$data]);
        } catch (InvalidArgumentException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw ValidationException::withMessages(['permission' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pengajuan izin berhasil dikirim dan menunggu keputusan sekolah.');
    }

    private function portalData(Student $student, int $schoolId): array
    {
        $class = \App\Models\ClassStudent::query()->where('student_id', $student->id)
            ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $schoolId))
            ->with('schoolClass.teacher')->first()?->schoolClass;
        $attendance = StudentAttendance::query()->where('school_id', $schoolId)->where('student_id', $student->id);
        $hadir = (clone $attendance)->where('status', 'H')->count();
        $sakit = (clone $attendance)->where('status', 'S')->count();
        $izin = (clone $attendance)->where('status', 'I')->count();
        $alpa = (clone $attendance)->where('status', 'A')->count();
        $total = $hadir + $sakit + $izin + $alpa;
        $savings = \App\Models\StudentSaving::query()->where('school_id', $schoolId)->where('student_id', $student->id)->latest('transaction_date')->get();

        return [
            'student' => $student,
            'schoolId' => $schoolId,
            'className' => $class?->name ?? 'Kelas belum terhubung',
            'homeroomTeacher' => $class?->teacher,
            'todayAttendance' => (clone $attendance)->whereDate('attendance_date', today())->first(),
            'attendanceHistory' => (clone $attendance)->latest('attendance_date')->limit(7)->get(),
            'permissionRequests' => AttendanceRequest::query()->where('school_id', $schoolId)->where('subject_type', Student::class)->where('subject_id', $student->id)->latest()->limit(5)->get(),
            'invoices' => \App\Models\Invoice::query()->where('school_id', $schoolId)->where('student_id', $student->id)->latest()->get(),
            'savings' => $savings,
            'savingsBalance' => $savings->sum(fn ($saving) => $saving->type === 'credit' ? $saving->amount : -$saving->amount),
            'stats' => ['hadir' => $hadir, 'sakit' => $sakit, 'izin' => $izin, 'alpa' => $alpa, 'rate' => $total ? round($hadir / $total * 100) : null],
        ];
    }

    private function portal(string $title, string $description, array $metrics): View
    {
        return view('pages.foundation.index', compact('title', 'description', 'metrics') + ['eyebrow' => 'Portal Pengguna']);
    }
}
