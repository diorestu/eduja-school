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
        $schoolId = $schoolContext->activeSchoolIdFor();
        $classes = app(\App\Services\ActiveEnrollment::class)->assignedClasses($schoolId, auth()->user())
            ->with(['students'=>fn ($q)=>$q->active()->where('students.school_id',$schoolId)])->get();
        $arrears = auth()->user()->hasRole('wali_kelas') ? \App\Models\Invoice::where('school_id',$schoolId)->whereIn('student_id',$classes->flatMap->students->pluck('id'))->get()->groupBy('student_id') : collect();
        [$start,$end] = app(\App\Services\PortalAttendancePeriod::class)->dates();
        $teacher = \App\Models\Teacher::active()->where('school_id',$schoolId)->where('user_id',auth()->id())->first();
        $history = \App\Models\TeacherAttendance::where('school_id',$schoolId)->where('teacher_id',$teacher?->id)
            ->whereBetween('attendance_date',[$start,$end])->latest('attendance_date')->paginate(25)->withQueryString();
        return view('pages.portal.guru', compact('classes','history','teacher','start','end','arrears'));
    }

    public function storeClassAttendance(Request $request, SchoolContext $context, AttendanceCommandService $attendance): RedirectResponse
    {
        $schoolId = $context->activeSchoolIdFor();
        $data = $request->validate(['school_class_id'=>['required','integer'],'student_id'=>['required','integer'],
            'attendance_date'=>['required','date_format:Y-m-d','before_or_equal:today'],'status'=>['required','in:H,S,I,D,A'],'note'=>['nullable','string','max:255']]);
        abort_unless(auth()->user()->hasRole('wali_kelas') && app(\App\Services\ActiveEnrollment::class)->assignedClasses($schoolId,$request->user())->whereKey($data['school_class_id'])->exists(),403);
        try { $attendance->recordStudent($schoolId,$data['student_id'],$data); }
        catch (InvalidArgumentException $e) { throw ValidationException::withMessages(['attendance'=>$e->getMessage()]); }
        return back()->with('success','Presensi kelas disimpan.');
    }

    public function storeTeacherAttendance(Request $request, SchoolContext $context, AttendanceCommandService $attendance): RedirectResponse
    {
        $schoolId = $context->activeSchoolIdFor($request->user());
        $teacher = \App\Models\Teacher::active()->where('school_id', $schoolId)->where('user_id', $request->user()->id)->first();
        if (! $teacher) {
            return back()->withErrors(['attendance' => 'Akun belum terhubung ke data GTK aktif di sekolah ini.']);
        }

        $data = $request->validate([
            'type' => ['required', 'in:masuk,pulang'],
            'location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ], ['location.latitude.required' => 'Lokasi perangkat diperlukan untuk presensi.', 'location.longitude.required' => 'Lokasi perangkat diperlukan untuk presensi.']);

        $gps = (new ConfiguredGpsAttendancePolicy(config('services.attendance.gps', [])))->evaluate(
            $schoolId,
            (float) $data['location']['latitude'],
            (float) $data['location']['longitude'],
        );
        if (! $gps['accepted']) {
            return back()->withErrors(['location' => $gps['reason']]);
        }

        $today = today()->toDateString();
        $existing = \App\Models\TeacherAttendance::where('school_id', $schoolId)->where('teacher_id', $teacher->id)->whereDate('attendance_date', $today)->first();
        if ($data['type'] === 'masuk' && $existing?->clock_in_at) {
            return back()->with('error', 'Presensi masuk sudah tercatat hari ini.');
        }
        if ($data['type'] === 'pulang' && ! $existing?->clock_in_at) {
            return back()->with('error', 'Presensi masuk perlu dicatat lebih dulu.');
        }
        if ($data['type'] === 'pulang' && $existing?->clock_out_at) {
            return back()->with('error', 'Presensi pulang sudah tercatat hari ini.');
        }

        $photoPath = $request->hasFile('photo') ? $request->file('photo')->store('attendance/teachers/'.$schoolId, 'local') : $existing?->photo_path;

        $attributes = [
            'attendance_date' => $today,
            'status' => $existing?->status ?? 'H',
            'source' => 'gps',
            'latitude' => $gps['latitude'],
            'longitude' => $gps['longitude'],
            'photo_path' => $photoPath,
        ];
        if ($data['type'] === 'masuk') {
            $attributes['clock_in_at'] = now()->toTimeString();
        } else {
            $attributes['clock_in_at'] = $existing->clock_in_at;
            $attributes['clock_out_at'] = now()->toTimeString();
        }

        $attendance->recordTeacher($schoolId, $teacher->id, $attributes);

        return back()->with('success', $data['type'] === 'masuk' ? 'Presensi masuk berhasil dicatat.' : 'Presensi pulang berhasil dicatat.');
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

        $classId = app(\App\Services\ActiveEnrollment::class)->forStudent($schoolId, $student->id)?->id;
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
        $class = app(\App\Services\ActiveEnrollment::class)->forStudent($schoolId, $student->id);
        $attendance = StudentAttendance::query()->where('school_id', $schoolId)->where('student_id', $student->id);
        [$start,$end] = app(\App\Services\PortalAttendancePeriod::class)->dates();
        $periodAttendance = (clone $attendance)->whereBetween('attendance_date',[$start,$end]);
        $hadir = (clone $periodAttendance)->where('status', 'H')->count();
        $sakit = (clone $periodAttendance)->where('status', 'S')->count();
        $izin = (clone $periodAttendance)->where('status', 'I')->count();
        $alpa = (clone $periodAttendance)->where('status', 'A')->count();
        $dispensasi = (clone $periodAttendance)->where('status', 'D')->count();
        $total = $hadir + $sakit + $izin + $alpa + $dispensasi;
        $savings = \App\Models\StudentSaving::query()->where('school_id', $schoolId)->where('student_id', $student->id)->latest('transaction_date')->get();

        return [
            'student' => $student,
            'schoolId' => $schoolId,
            'guardianFinanceAllowed' => (bool) \App\Models\School::whereKey($schoolId)->value('guardian_finance_allowed'),
            'className' => $class?->name ?? 'Kelas belum terhubung',
            'homeroomTeacher' => $class?->teacher,
            'todayAttendance' => (clone $attendance)->whereDate('attendance_date', today())->first(),
            'attendanceHistory' => (clone $periodAttendance)->latest('attendance_date')->paginate(25)->withQueryString(),
            'periodStart' => $start, 'periodEnd' => $end,
            'permissionRequests' => AttendanceRequest::query()->where('school_id', $schoolId)->where('subject_type', Student::class)->where('subject_id', $student->id)->latest()->limit(5)->get(),
            'invoices' => \App\Models\Invoice::ownedBySchool($schoolId)->where('student_id', $student->id)->latest()->get(),
            'bankAccounts' => \App\Models\SchoolAccount::where('school_id',$schoolId)->where('is_active',true)->whereRaw('LOWER(type) = ?', ['bank'])->orderBy('name')->get(),
            'paymentSubmissions' => \App\Models\PaymentSubmission::where('school_id',$schoolId)->where('student_id',$student->id)->latest()->get(),
            'savings' => $savings,
            'savingsBalance' => $savings->sum(fn ($saving) => $saving->type === 'credit' ? $saving->amount : -$saving->amount),
            'stats' => ['dispensasi' => $dispensasi, 'hadir' => $hadir, 'sakit' => $sakit, 'izin' => $izin, 'alpa' => $alpa, 'rate' => $total ? round($hadir / $total * 100) : null],
        ];
    }

    private function portal(string $title, string $description, array $metrics): View
    {
        return view('pages.foundation.index', compact('title', 'description', 'metrics') + ['eyebrow' => 'Portal Pengguna']);
    }
}
