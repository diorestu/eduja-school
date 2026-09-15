<?php

namespace App\Http\Controllers;

use App\Exceptions\OutstandingStudentBills;
use App\Models\Alumni;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AcademicLifecycleService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicFoundationController extends Controller
{
    public function departments(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.foundation.index', [
            'title' => 'Jurusan & Program Keahlian',
            'eyebrow' => 'Akademik Lanjutan',
            'description' => 'Kelola jurusan sebagai fondasi rombel, kenaikan kelas, dan kelulusan.',
            'metrics' => [
                ['label' => 'Jurusan Aktif', 'value' => Department::where('school_id', $schoolId)->where('is_active', true)->count()],
                ['label' => 'Rombel', 'value' => SchoolClass::where('school_id', $schoolId)->count()],
                ['label' => 'Siswa Aktif', 'value' => Student::where('school_id', $schoolId)->where('status', 'active')->count()],
            ],
            'rows' => Department::where('school_id', $schoolId)->latest()->get(['code', 'name', 'is_active']),
            'columns' => ['code' => 'Kode', 'name' => 'Jurusan', 'is_active' => 'Aktif'],
            'form' => [
                'action' => route('academic.departments.store'),
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'placeholder' => 'IPA'],
                    ['name' => 'name', 'label' => 'Nama Jurusan', 'placeholder' => 'Ilmu Pengetahuan Alam'],
                ],
                'button' => 'Tambah Jurusan',
            ],
        ]);
    }

    public function storeDepartment(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        if (! in_array(optional($schoolContext->activeSchool())->level, ['smk', 'mak'], true)) {
            return back()->withErrors(['code' => 'Jurusan hanya tersedia untuk SMK/MAK.'])->withInput();
        }
        Department::updateOrCreate(
            ['school_id' => $schoolId, 'code' => strtoupper($validated['code'])],
            ['name' => $validated['name'], 'is_active' => true],
        );

        return back()->with('success', 'Jurusan berhasil disimpan.');
    }

    public function promotion(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.foundation.index', [
            'title' => 'Kenaikan Kelas',
            'eyebrow' => 'Wizard Akademik',
            'description' => 'Alur bertahap untuk validasi siswa aktif, tujuan rombel, dan arsip keputusan.',
            'metrics' => [
                ['label' => 'Siswa Aktif', 'value' => Student::where('school_id', $schoolId)->where('status', 'active')->count()],
                ['label' => 'Rombel Tujuan', 'value' => SchoolClass::where('school_id', $schoolId)->count()],
            ],
            'rows' => Student::where('school_id', $schoolId)->where('status', 'active')->latest()->get(['id', 'nis', 'name', 'status']),
            'columns' => ['nis' => 'NIS', 'name' => 'Nama', 'status' => 'Status'],
            'sections' => [
                ['title' => 'Tahap 1', 'items' => ['Pilih tahun ajaran dan kelas asal.']],
                ['title' => 'Tahap 2', 'items' => ['Validasi siswa naik kelas, tinggal kelas, atau pindah.']],
                ['title' => 'Tahap 3', 'items' => ['Buat rombel tujuan dan simpan berita acara.']],
            ],
            'form' => [
                'action' => route('academic.promotion.store'),
                'fields' => [
                    [
                        'name' => 'student_ids[]',
                        'label' => 'Siswa',
                        'type' => 'select',
                        'multiple' => true,
                        'options' => Student::where('school_id', $schoolId)->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
                    ],
                    [
                        'name' => 'target_class_id',
                        'label' => 'Kelas Tujuan',
                        'type' => 'select',
                        'options' => SchoolClass::where('school_id', $schoolId)->orderBy('grade')->orderBy('name')->pluck('name', 'id')->all(),
                    ],
                ],
                'button' => 'Proses Kenaikan',
            ],
        ]);
    }

    public function promote(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
            'target_class_id' => ['required', 'integer'],
        ]);

        $count = $service->promote($schoolId, $validated['student_ids'], $validated['target_class_id'], $request->user()?->id);

        if ($count === 0) {
            return back()->with('error', 'Tidak ada siswa aktif yang valid untuk diproses.');
        }

        $targetClass = SchoolClass::where('school_id', $schoolId)->findOrFail($validated['target_class_id']);

        return back()->with('success', $count.' siswa berhasil dipindahkan ke '.$targetClass->name.'.');
    }

    public function graduation(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.foundation.index', [
            'title' => 'Kelulusan',
            'eyebrow' => 'Wizard Akademik',
            'description' => 'Kelulusan mengecek tunggakan, menutup presensi, dan membuat profil alumni.',
            'metrics' => [
                ['label' => 'Kandidat', 'value' => Student::where('school_id', $schoolId)->where('status', 'active')->count()],
                ['label' => 'Alumni', 'value' => Alumni::where('school_id', $schoolId)->count()],
            ],
            'rows' => Student::where('school_id', $schoolId)->where('status', 'active')->latest()->get(['id', 'nis', 'name', 'status']),
            'columns' => ['nis' => 'NIS', 'name' => 'Nama', 'status' => 'Status'],
            'sections' => [
                ['title' => 'Kontrol Kelulusan', 'items' => ['Cek tunggakan sebelum lulus.', 'Nonaktifkan absensi setelah status graduated.', 'Buat data alumni otomatis dari siswa.']],
            ],
            'form' => [
                'action' => route('academic.graduation.store'),
                'fields' => [
                    [
                        'name' => 'student_ids[]',
                        'label' => 'Siswa',
                        'type' => 'select',
                        'multiple' => true,
                        'options' => Student::where('school_id', $schoolId)->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
                    ],
                    ['name' => 'graduation_date', 'label' => 'Tanggal Lulus', 'type' => 'date'],
                    ['name' => 'graduation_year', 'label' => 'Tahun Lulus', 'type' => 'number', 'placeholder' => now()->year],
                ],
                'button' => 'Proses Kelulusan',
            ],
        ]);
    }

    public function graduate(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
            'graduation_date' => ['required', 'date'],
            'graduation_year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ]);

        try {
            $count = $service->graduate(
                $schoolId,
                $validated['student_ids'],
                $validated['graduation_date'],
                $validated['graduation_year'],
                $request->user()?->id,
            );
        } catch (OutstandingStudentBills $exception) {
            return back()->with('error', 'Kelulusan dibatalkan. Siswa masih memiliki tunggakan: '.implode(', ', $exception->studentNames).'.');
        }

        if ($count === 0) {
            return back()->with('error', 'Tidak ada siswa aktif yang valid untuk diproses.');
        }

        return back()->with('success', $count.' siswa berhasil diluluskan dan dipindahkan ke alumni.');
    }

    public function alumni(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $isAlumni = request()->user()->hasRole(['alumni']);
        $alumni = Alumni::where('school_id', $schoolId)
            ->when($isAlumni, fn ($q) => $q->where('user_id', request()->user()->id))->latest()->get();

        return view('pages.foundation.alumni', [
            'title' => 'Alumni',
            'eyebrow' => 'Tracer Study',
            'description' => 'Profil alumni, pendidikan lanjutan, pekerjaan, dan pembaruan tracer study.',
            'alumni' => $alumni,
            'isAlumni' => $isAlumni,
            'editableAlumni' => $isAlumni ? $alumni->firstWhere('user_id', request()->user()->id) : null,
        ]);
    }

    public function updateAlumni(Request $request, Alumni $alumni, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($alumni->school_id === $schoolId, 404);

        if ($request->user()->hasRole(['alumni'])) {
            abort_unless($alumni->user_id === $request->user()->id, 403);
        }

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'current_status' => ['required', 'string', 'max:60'],
            'education_history' => ['nullable', 'string', 'max:2000'],
            'current_job' => ['nullable', 'string', 'max:160'],
        ]);

        $alumni->update($validated);

        return back()->with('success', 'Profil alumni berhasil diperbarui.');
    }
}
