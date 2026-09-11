<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRequest;
use App\Models\BillingItem;
use App\Models\PaymentSubmission;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Services\SchoolContext;
use App\Services\IdentityResolver;
use Illuminate\View\View;

class PortalFoundationController extends Controller
{
    public function guru(SchoolContext $schoolContext): View
    {
        return $this->portal('Portal Guru & Wali Kelas', 'Dashboard kelas, presensi siswa, tunggakan wali kelas, dan approval izin.', [
            ['label' => 'Siswa Aktif', 'value' => Student::where('school_id', $schoolContext->activeSchoolId())->where('status', 'active')->count()],
            ['label' => 'Izin Pending', 'value' => AttendanceRequest::where('school_id', $schoolContext->activeSchoolId())->where('status', 'pending')->count()],
        ]);
    }

    public function siswa(SchoolContext $schoolContext, IdentityResolver $identityResolver): View
    {
        $schoolId = $schoolContext->activeSchoolId();
        
        $user = auth()->user();
        $student = $identityResolver->studentFor($user, $schoolId);
        if (! $student) return view('pages.portal.empty-identity', ['title' => 'Portal Siswa', 'message' => 'Akun ini belum terhubung ke data siswa di sekolah aktif.', 'action' => 'Hubungi admin sekolah untuk menghubungkan akun siswa.']);

        $classStudent = \App\Models\ClassStudent::where('student_id', $student->id)->whereHas('schoolClass', fn ($query) => $query->where('school_id', $schoolId))->first();
        $class = $classStudent ? \App\Models\SchoolClass::find($classStudent->school_class_id) : null;
        $className = $class ? $class->name : 'Kelas belum terhubung';

        $classLocation = null;
        if ($class) {
            $classLocation = $class->location;
        }

        $homeroomTeacher = $class ? $class->teacher : null;

        $todayStr = today()->toDateString();
        $todayAttendance = StudentAttendance::where('student_id', $student->id)
            ->whereDate('attendance_date', $todayStr)
            ->first();

        $attendanceHistory = StudentAttendance::where('student_id', $student->id)
            ->orderBy('attendance_date', 'desc')
            ->limit(7)
            ->get();

        $totalHadir = StudentAttendance::where('student_id', $student->id)->where('status', 'H')->count();
        $totalSakit = StudentAttendance::where('student_id', $student->id)->where('status', 'S')->count();
        $totalIzin = StudentAttendance::where('student_id', $student->id)->where('status', 'I')->count();
        $totalAlpa = StudentAttendance::where('student_id', $student->id)->where('status', 'A')->count();
        $totalDays = $totalHadir + $totalSakit + $totalIzin + $totalAlpa;
        $attendanceRate = $totalDays > 0 ? round(($totalHadir / $totalDays) * 100) : 100;

        $permissionRequests = AttendanceRequest::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('pages.portal.siswa', [
            'title' => 'Portal Siswa',
            'student' => $student,
            'className' => $className,
            'classLocation' => $classLocation,
            'homeroomTeacher' => $homeroomTeacher,
            'todayAttendance' => $todayAttendance,
            'attendanceHistory' => $attendanceHistory,
            'permissionRequests' => $permissionRequests,
            'stats' => [
                'hadir' => $totalHadir,
                'sakit' => $totalSakit,
                'izin' => $totalIzin,
                'alpa' => $totalAlpa,
                'rate' => $attendanceRate
            ],
            'schoolId' => $schoolId,
        ]);
    }

    public function storeAttendance(SchoolContext $schoolContext, \Illuminate\Http\Request $request, IdentityResolver $identityResolver)
    {
        $schoolId = $schoolContext->activeSchoolId();
        $user = auth()->user();

        $student = $identityResolver->studentFor($user, $schoolId);

        if (!$student) {
            return back()->with('error', 'Siswa tidak ditemukan.');
        }

        $classStudent = \App\Models\ClassStudent::where('student_id', $student->id)->first();
        $classId = $classStudent ? $classStudent->school_class_id : null;

        $type = $request->input('type');
        $todayStr = today()->toDateString();
        $timeNow = now()->toTimeString();

        $attendance = StudentAttendance::where('student_id', $student->id)
            ->whereDate('attendance_date', $todayStr)
            ->first();

        if ($type === 'masuk') {
            if ($attendance && $attendance->clock_in_at) {
                return back()->with('error', 'Anda sudah melakukan presensi masuk hari ini.');
            }

            StudentAttendance::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'attendance_date' => $todayStr,
                ],
                [
                    'school_id' => $schoolId,
                    'school_class_id' => $classId,
                    'status' => 'H',
                    'clock_in_at' => $timeNow,
                    'source' => 'web',
                    'latitude' => -6.200000,
                    'longitude' => 106.816666,
                ]
            );

            return back()->with('success', 'Presensi masuk berhasil dilakukan pada pukul ' . substr($timeNow, 0, 5));
        } elseif ($type === 'pulang') {
            if (!$attendance || !$attendance->clock_in_at) {
                return back()->with('error', 'Silakan lakukan presensi masuk terlebih dahulu.');
            }
            if ($attendance->clock_out_at) {
                return back()->with('error', 'Anda sudah melakukan presensi pulang hari ini.');
            }

            $attendance->update([
                'clock_out_at' => $timeNow,
            ]);

            return back()->with('success', 'Presensi pulang berhasil dilakukan pada pukul ' . substr($timeNow, 0, 5));
        }

        return back()->with('error', 'Aksi tidak valid.');
    }

    public function storePermission(SchoolContext $schoolContext, \Illuminate\Http\Request $request, IdentityResolver $identityResolver)
    {
        $schoolId = $schoolContext->activeSchoolId();
        $user = auth()->user();

        $student = $identityResolver->studentFor($user, $schoolId);

        if (!$student) {
            return back()->with('error', 'Siswa tidak ditemukan.');
        }

        $request->validate([
            'request_type' => 'required|in:Izin,Sakit',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|image|max:5120',
        ]);

        $documentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $documentPath = $file->store('attendance-documents', 'public');
        }

        AttendanceRequest::create([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'submitted_by' => auth()->id(),
            'request_type' => $request->request_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date ?? $request->start_date,
            'reason' => $request->reason,
            'document_path' => $documentPath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan ' . $request->request_type . ' berhasil dikirim dan sedang menunggu persetujuan.');
    }

    public function orangTua(SchoolContext $schoolContext, \Illuminate\Http\Request $request): View
    {
        $schoolId = $schoolContext->activeSchoolId();
        $user = auth()->user();

        $students = Student::where('school_id', $schoolId)->where('guardian_user_id', $user->id)->where('is_active', true)->get();
        if ($students->isEmpty()) return view('pages.portal.empty-identity', ['title' => 'Portal Orang Tua', 'message' => 'Belum ada data siswa yang terhubung ke akun ini.', 'action' => 'Minta admin sekolah menghubungkan data siswa ke akun wali murid.']);

        $selectedStudentId = $request->query('student_id', $students->first()?->id);
        $student = $students->firstWhere('id', $selectedStudentId) ?? $students->first();

        if (! $student) return view('pages.portal.empty-identity', ['title' => 'Portal Orang Tua', 'message' => 'Siswa yang dipilih tidak terhubung ke akun ini.', 'action' => 'Pilih siswa lain atau hubungi admin sekolah.']);

        $classStudent = \App\Models\ClassStudent::where('student_id', $student->id)->whereHas('schoolClass', fn ($query) => $query->where('school_id', $schoolId))->first();
        $class = $classStudent ? \App\Models\SchoolClass::find($classStudent->school_class_id) : null;
        $className = $class ? $class->name : 'Kelas belum terhubung';

        $classLocation = null;
        if ($class) {
            $classLocation = $class->location;
        }

        $homeroomTeacher = $class ? $class->teacher : null;

        $todayStr = today()->toDateString();
        $todayAttendance = StudentAttendance::where('student_id', $student->id)
            ->whereDate('attendance_date', $todayStr)
            ->first();

        $attendanceHistory = StudentAttendance::where('student_id', $student->id)
            ->orderBy('attendance_date', 'desc')
            ->limit(7)
            ->get();

        $totalHadir = StudentAttendance::where('student_id', $student->id)->where('status', 'H')->count();
        $totalSakit = StudentAttendance::where('student_id', $student->id)->where('status', 'S')->count();
        $totalIzin = StudentAttendance::where('student_id', $student->id)->where('status', 'I')->count();
        $totalAlpa = StudentAttendance::where('student_id', $student->id)->where('status', 'A')->count();
        $totalDays = $totalHadir + $totalSakit + $totalIzin + $totalAlpa;
        $attendanceRate = $totalDays > 0 ? round(($totalHadir / $totalDays) * 100) : 100;

        $invoices = \App\Models\Invoice::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $permissionRequests = AttendanceRequest::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $savings = \App\Models\StudentSaving::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $savingsBalance = 0;
        foreach ($savings as $saving) {
            if ($saving->type === 'credit') {
                $savingsBalance += $saving->amount;
            } elseif ($saving->type === 'debit') {
                $savingsBalance -= $saving->amount;
            }
        }

        return view('pages.portal.orang-tua', [
            'title' => 'Portal Orang Tua',
            'students' => $students,
            'student' => $student,
            'className' => $className,
            'classLocation' => $classLocation,
            'homeroomTeacher' => $homeroomTeacher,
            'todayAttendance' => $todayAttendance,
            'attendanceHistory' => $attendanceHistory,
            'permissionRequests' => $permissionRequests,
            'invoices' => $invoices,
            'savings' => $savings,
            'savingsBalance' => $savingsBalance,
            'stats' => [
                'hadir' => $totalHadir,
                'sakit' => $totalSakit,
                'izin' => $totalIzin,
                'alpa' => $totalAlpa,
                'rate' => $attendanceRate
            ],
            'schoolId' => $schoolId,
        ]);
    }

    private function portal(string $title, string $description, array $metrics): View
    {
        return view('pages.foundation.index', [
            'title' => $title,
            'eyebrow' => 'Portal Pengguna',
            'description' => $description,
            'metrics' => $metrics,
            'sections' => [
                ['title' => 'Akses Terbatas', 'items' => ['Data ditampilkan sesuai active school dan role aktif.', 'Kontrol berikutnya memakai relasi wali kelas, siswa, atau orang tua.']],
            ],
        ]);
    }
}
