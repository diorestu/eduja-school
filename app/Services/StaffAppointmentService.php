<?php

namespace App\Services;

use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffAppointmentService
{
    public const ROLES = ['kepsek' => 'Kepala Sekolah', 'wakasek' => 'Wakil Kepala Sekolah', 'bendahara' => 'Bendahara', 'teller' => 'Teller', 'tu' => 'Tata Usaha', 'staf_tu' => 'Staf TU', 'wali_kelas' => 'Wali Kelas'];

    public function appoint(int $schoolId, int $teacherId, array $attributes, ?int $actorId): void
    {
        DB::transaction(function () use ($schoolId, $teacherId, $attributes, $actorId) {
            $teacher = Teacher::where('school_id', $schoolId)->active()->lockForUpdate()->findOrFail($teacherId);
            if (! $teacher->user_id) {
                throw ValidationException::withMessages(['teacher_id' => 'Hubungkan GTK dengan akun sekolah sebelum pengangkatan.']);
            }
            if (DB::table('staff_appointments')->where('school_id', $schoolId)->where('teacher_id', $teacherId)->whereNull('ends_on')->exists()) {
                throw ValidationException::withMessages(['role' => 'Akhiri jabatan yang sedang berlangsung terlebih dahulu.']);
            }
            if (DB::table('school_user_roles')->where('school_id', $schoolId)->where('user_id', $teacher->user_id)->whereIn('role', array_keys(self::ROLES))->where('is_active', true)->where('membership_status', 'active')->exists()) {
                throw ValidationException::withMessages(['role' => 'Akhiri role jabatan yang sedang aktif terlebih dahulu.']);
            }
            $lastEnd = DB::table('staff_appointments')->where('school_id', $schoolId)->where('teacher_id', $teacherId)->max('ends_on');
            if ($lastEnd && $attributes['starts_on'] <= $lastEnd) {
                throw ValidationException::withMessages(['starts_on' => 'Pengangkatan baru harus setelah akhir jabatan sebelumnya.']);
            }
            $existing = DB::table('school_user_roles')->where('school_id', $schoolId)->where('user_id', $teacher->user_id)->where('role', $attributes['role'])->first();
            if ($existing && $existing->is_active && $existing->membership_status === 'active') {
                throw ValidationException::withMessages(['role' => 'Role ini sudah aktif. Akhiri jabatan lama sebelum mengangkat kembali.']);
            }
            DB::table('staff_appointments')->insert([
                'school_id' => $schoolId, 'teacher_id' => $teacherId, 'role' => $attributes['role'],
                'appointment_number' => $attributes['appointment_number'], 'starts_on' => $attributes['starts_on'],
                'appointing_institution' => $attributes['appointing_institution'], 'document_path' => $attributes['document_path'],
                'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('school_user_roles')->updateOrInsert(
                ['school_id' => $schoolId, 'user_id' => $teacher->user_id, 'role' => $attributes['role']],
                ['is_active' => true, 'membership_status' => 'active', 'lifecycle_suspended_by' => null, 'updated_at' => now(), 'created_at' => $existing?->created_at ?? now()],
            );
        });
    }

    public function end(int $schoolId, int $teacherId, string $endsOn, ?string $role = null): void
    {
        DB::transaction(function () use ($schoolId, $teacherId, $endsOn, $role) {
            $teacher = Teacher::where('school_id', $schoolId)->lockForUpdate()->findOrFail($teacherId);
            $appointment = DB::table('staff_appointments')->where('school_id', $schoolId)->where('teacher_id', $teacherId)->whereNull('ends_on')->lockForUpdate()->first();
            if (! $appointment) {
                $legacy = DB::table('school_user_roles')->where('school_id', $schoolId)->where('user_id', $teacher->user_id)->where('role', $role)
                    ->whereIn('role', array_keys(self::ROLES))->where(fn ($q) => $q->where('is_active', true)->orWhereNotNull('lifecycle_suspended_by'))->first();
                if (! $legacy) throw ValidationException::withMessages(['teacher_id' => 'Tidak ada jabatan aktif untuk diakhiri.']);
                DB::table('school_user_roles')->where('id', $legacy->id)->update(['is_active' => false, 'membership_status' => 'inactive', 'lifecycle_suspended_by' => null, 'updated_at' => now()]);
                DB::table('lifecycle_histories')->insert(['school_id' => $schoolId, 'subject_type' => 'staff_appointment', 'subject_id' => $teacherId, 'from_status' => $role, 'to_status' => 'ended', 'effective_date' => $endsOn, 'metadata' => json_encode(['legacy_role' => true]), 'created_at' => now(), 'updated_at' => now()]);
                return;
            }
            if ($endsOn < $appointment->starts_on) {
                throw ValidationException::withMessages(['ends_on' => 'Tanggal akhir tidak boleh sebelum tanggal pengangkatan.']);
            }
            DB::table('staff_appointments')->where('id', $appointment->id)->update(['ends_on' => $endsOn, 'updated_at' => now()]);
            DB::table('school_user_roles')->where('school_id', $schoolId)->where('user_id', $teacher->user_id)->where('role', $appointment->role)
                ->update(['is_active' => false, 'membership_status' => 'inactive', 'lifecycle_suspended_by' => null, 'updated_at' => now()]);
        });
    }
}
