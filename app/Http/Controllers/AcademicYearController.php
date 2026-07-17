<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index()
    {
        $academicYears = AcademicYear::orderBy('year', 'desc')->get();
        return view('pages.kesiswaan.akademik', [
            'title' => 'Tahun Akademik',
            'academicYears' => $academicYears
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'year' => 'required|string',
            'semester' => 'required|in:Ganjil,Genap',
            'is_active' => 'nullable|boolean'
        ]);

        $is_active = $request->has('is_active') ? (bool)$request->is_active : false;

        if ($is_active) {
            // Deactivate all others first
            AcademicYear::query()->update(['is_active' => false]);
        }

        AcademicYear::create([
            'year' => $request->year,
            'semester' => $request->semester,
            'is_active' => $is_active,
        ]);

        return redirect()->route('akademik.index')->with('success', 'Tahun Akademik berhasil ditambahkan.');
    }

    public function toggleActive($id)
    {
        $academicYear = AcademicYear::findOrFail($id);
        
        // Deactivate all others
        AcademicYear::query()->update(['is_active' => false]);

        // Activate this one
        $academicYear->update(['is_active' => true]);

        return redirect()->route('akademik.index')->with('success', 'Tahun Akademik aktif berhasil diubah.');
    }
}
