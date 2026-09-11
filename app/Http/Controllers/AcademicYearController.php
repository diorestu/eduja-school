<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\AcademicLifecycleService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $academicYears = AcademicYear::where('school_id', $schoolId)->orderByDesc('year')->orderByDesc('semester')->get();

        return view('pages.kesiswaan.akademik', [
            'title' => 'Tahun Akademik',
            'academicYears' => $academicYears,
        ]);
    }

    public function store(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service)
    {
        $validated = $request->validate([
            'year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:Ganjil,Genap'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $service->createAcademicYear($schoolId, $validated);

        return redirect()->route('akademik.index')->with('success', 'Tahun Akademik berhasil ditambahkan.');
    }

    public function toggleActive(int $id, SchoolContext $schoolContext, AcademicLifecycleService $service)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $service->activateAcademicYear($schoolId, $id);

        return redirect()->route('akademik.index')->with('success', 'Tahun Akademik aktif berhasil diubah.');
    }
}
