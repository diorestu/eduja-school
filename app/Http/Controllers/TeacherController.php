<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\AcademicLifecycleService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\StaffAppointmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    public function index(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $teachers = Teacher::where('school_id', $schoolId)->orderBy('name')->get();

        return view('pages.kesiswaan.guru', [
            'lifecycleHistories' => \Illuminate\Support\Facades\DB::table('lifecycle_histories')->where('school_id', $schoolId)->where('subject_type', 'teacher')->orderByDesc('id')->get()->groupBy('subject_id'),
            'title' => 'Data GTK (Guru & Staf)',
            'teachers' => $teachers,
            'appointments' => DB::table('staff_appointments')->where('school_id', $schoolId)->orderByDesc('id')->get()->groupBy('teacher_id'),
            'appointmentRoles' => StaffAppointmentService::ROLES,
            'staffRoleMemberships' => DB::table('school_user_roles')->where('school_id', $schoolId)->whereIn('role', array_keys(StaffAppointmentService::ROLES))->where(fn ($q) => $q->where('is_active', true)->orWhereNotNull('lifecycle_suspended_by'))->get()->groupBy('user_id'),
            'linkableAccounts' => \App\Models\User::whereHas('schoolRoles', fn ($q) => $q->where('school_id', $schoolId)->whereIn('role', ['guru', 'tendik', 'kepsek', 'wakasek', 'bendahara', 'teller', 'tu', 'staf_tu', 'wali_kelas'])->where('is_active', true)->where('membership_status', 'active'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);

        if ($request->input('action') === 'appoint') {
            $data = $request->validate([
                'teacher_id' => ['required', 'integer'], 'role' => ['required', Rule::in(array_keys(StaffAppointmentService::ROLES))],
                'appointment_number' => ['required', 'string', 'max:255'], 'starts_on' => ['required', 'date'],
                'appointing_institution' => ['required', 'string', 'max:255'],
                'appointment_document' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            ]);
            Teacher::where('school_id', $schoolId)->findOrFail($data['teacher_id']);
            $path = $request->file('appointment_document')->store('appointments/'.$schoolId, 'local');
            try {
                app(StaffAppointmentService::class)->appoint($schoolId, $data['teacher_id'], [...$data, 'document_path' => $path], $request->user()->id);
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
            return back()->with('success', 'Pengangkatan jabatan disimpan.');
        }
        if ($request->input('action') === 'end_appointment') {
            $data = $request->validate(['teacher_id' => ['required', 'integer'], 'ends_on' => ['required', 'date'], 'role' => ['nullable', Rule::in(array_keys(StaffAppointmentService::ROLES))]]);
            app(StaffAppointmentService::class)->end($schoolId, $data['teacher_id'], $data['ends_on'], $data['role'] ?? null);
            return back()->with('success', 'Jabatan diakhiri; riwayat tetap tersimpan.');
        }
        if ($request->input('action') === 'status') {
            $validated = $request->validate([
                'teacher_id' => ['required', 'integer'],
                'status' => ['required', Rule::in(Teacher::STATUSES)],
                'status_date' => ['required', 'date'],
                'status_note' => ['nullable', 'string', 'max:1000'],
                'contract_renewed' => ['required_if:status,contract_ended', 'nullable', 'boolean'],
                'contract_start_date' => ['required_if:contract_renewed,1', 'nullable', 'date'],
                'contract_end_date' => ['required_if:contract_renewed,1', 'nullable', 'date', 'after:contract_start_date'],
                'status_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);
            Teacher::where('school_id', $schoolId)->findOrFail($validated['teacher_id']);
            $metadata = array_intersect_key($validated, array_flip(['contract_renewed', 'contract_start_date', 'contract_end_date']));
            if ($request->hasFile('status_document')) {
                $metadata['status_document_path'] = $request->file('status_document')->store('lifecycle/'.$schoolId.'/teacher/'.$validated['teacher_id'], 'local');
            }
            if ($validated['status'] === 'contract_ended' && $request->boolean('contract_renewed')) {
                $validated['status'] = 'active';
            }
            try {
            $service->transitionTeacher(
                $schoolId,
                $validated['teacher_id'],
                $validated['status'],
                $validated['status_date'],
                $validated['status_note'] ?? null,
                $metadata,
                $request->user()?->id,
            );

            } catch (\Throwable $exception) {
                if (isset($metadata['status_document_path'])) Storage::disk('local')->delete($metadata['status_document_path']);
                throw $exception;
            }
            return back()->with('success', 'Status guru/tendik berhasil diperbarui.');
        }

        $existingTeacher = $request->input('action') === 'update' ? Teacher::where('school_id', $schoolId)->findOrFail($request->integer('teacher_id')) : null;
        $validated = $request->validate([
            'nuptk' => ['nullable', 'string', Rule::unique('teachers', 'nuptk')->ignore($existingTeacher?->id)],
            'nip' => ['nullable', 'string', Rule::unique('teachers', 'nip')->ignore($existingTeacher?->id)],
            'name' => ['required', 'string'],
            'role_type' => ['required', 'string'],
            'staff_type' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', Rule::exists('school_user_roles', 'user_id')->where('school_id', $schoolId)->where('is_active', true)->where('membership_status', 'active')->whereIn('role', ['guru', 'tendik', 'kepsek', 'wakasek', 'bendahara', 'teller', 'tu', 'staf_tu', 'wali_kelas']), Rule::unique('teachers', 'user_id')->where('school_id', $schoolId)->ignore($existingTeacher?->id)],
            'phone' => ['nullable', 'string'],
        ]);

        if ($existingTeacher) {
            $existingTeacher->update($validated);
            return back()->with('success', 'Data GTK berhasil diperbarui.');
        }
        Teacher::create([...$validated, 'school_id' => $schoolId, 'status' => 'active', 'is_active' => true]);

        return redirect()->route('guru.index')->with('success', 'GTK berhasil ditambahkan.');
    }
    public function document(int $teacher, int $record, string $kind, SchoolContext $context)
    {
        $schoolId = $context->activeSchoolIdFor();
        Teacher::where('school_id', $schoolId)->findOrFail($teacher);
        if ($kind === 'appointment') {
            $path = DB::table('staff_appointments')->where('school_id', $schoolId)->where('teacher_id', $teacher)->where('id', $record)->value('document_path');
        } elseif ($kind === 'lifecycle') {
            $metadata = DB::table('lifecycle_histories')->where('school_id', $schoolId)->where('subject_type', 'teacher')->where('subject_id', $teacher)->where('id', $record)->value('metadata');
            $path = (json_decode($metadata ?? '{}', true) ?? [])['status_document_path'] ?? null;
        } else {
            abort(404);
        }
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path);
    }

}
