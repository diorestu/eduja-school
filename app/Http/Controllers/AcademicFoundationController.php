<?php

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Models\ClassStudent;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcademicFoundationController extends Controller
{
    public function departments(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolId();

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

    public function storeDepartment(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        Department::updateOrCreate(
            ['school_id' => $schoolContext->activeSchoolId(), 'code' => strtoupper($validated['code'])],
            ['name' => $validated['name'], 'is_active' => true],
        );

        return back()->with('success', 'Jurusan berhasil disimpan.');
    }

    public function promotion(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolId();

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

    public function promote(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolId();
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
            'target_class_id' => ['required', 'integer'],
        ]);

        $targetClass = SchoolClass::where('school_id', $schoolId)->findOrFail($validated['target_class_id']);
        $studentIds = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereIn('id', $validated['student_ids'])
            ->pluck('id')
            ->all();

        if ($studentIds === []) {
            return back()->with('error', 'Tidak ada siswa aktif yang valid untuk diproses.');
        }

        DB::transaction(function () use ($studentIds, $targetClass) {
            ClassStudent::whereIn('student_id', $studentIds)
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $targetClass->academic_year_id))
                ->delete();

            foreach ($studentIds as $studentId) {
                ClassStudent::updateOrCreate([
                    'student_id' => $studentId,
                    'school_class_id' => $targetClass->id,
                ]);
            }
        });

        return back()->with('success', count($studentIds).' siswa berhasil dipindahkan ke '.$targetClass->name.'.');
    }

    public function graduation(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolId();

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

    public function graduate(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolId();
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
            'graduation_date' => ['required', 'date'],
            'graduation_year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ]);

        $students = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereIn('id', $validated['student_ids'])
            ->get();

        if ($students->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa aktif yang valid untuk diproses.');
        }

        $blockedNames = Invoice::where('school_id', $schoolId)
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', '!=', 'Lunas')
            ->with('student:id,name')
            ->get()
            ->pluck('student.name')
            ->filter()
            ->unique()
            ->values();

        if ($blockedNames->isNotEmpty()) {
            return back()->with('error', 'Kelulusan dibatalkan. Siswa masih memiliki tunggakan: '.$blockedNames->join(', ').'.');
        }

        DB::transaction(function () use ($students, $schoolId, $validated) {
            foreach ($students as $student) {
                $student->update([
                    'status' => 'graduated',
                    'is_active' => false,
                    'status_date' => $validated['graduation_date'],
                    'status_note' => 'Lulus tahun '.$validated['graduation_year'],
                ]);

                Alumni::updateOrCreate(
                    ['school_id' => $schoolId, 'student_id' => $student->id],
                    [
                        'name' => $student->name,
                        'nisn' => $student->nisn,
                        'graduation_year' => $validated['graduation_year'],
                        'phone' => $student->phone,
                        'current_status' => 'Lulus',
                    ],
                );
            }

            ClassStudent::whereIn('student_id', $students->pluck('id'))->delete();
        });

        return back()->with('success', $students->count().' siswa berhasil diluluskan dan dipindahkan ke alumni.');
    }

    public function alumni(SchoolContext $schoolContext): View
    {
        return view('pages.foundation.index', [
            'title' => 'Alumni',
            'eyebrow' => 'Tracer Study',
            'description' => 'Profil alumni, pendidikan lanjutan, pekerjaan, dan pembaruan tracer study.',
            'metrics' => [
                ['label' => 'Total Alumni', 'value' => Alumni::where('school_id', $schoolContext->activeSchoolId())->count()],
            ],
            'rows' => Alumni::where('school_id', $schoolContext->activeSchoolId())->latest()->get(['name', 'graduation_year', 'current_status', 'current_job']),
            'columns' => ['name' => 'Nama', 'graduation_year' => 'Angkatan', 'current_status' => 'Status', 'current_job' => 'Pekerjaan'],
        ]);
    }
}
