<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\Teacher;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index()
    {
        $classes = SchoolClass::with(['academicYear', 'teacher'])->orderBy('name', 'asc')->get();
        $academicYears = AcademicYear::all();
        $teachers = Teacher::where('role_type', 'Guru')->where('is_active', true)->get();

        return view('pages.kesiswaan.kelas', [
            'title' => 'Data Kelas',
            'classes' => $classes,
            'academicYears' => $academicYears,
            'teachers' => $teachers
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'grade' => 'required|integer',
            'academic_year_id' => 'required|exists:academic_years,id',
            'teacher_id' => 'nullable|exists:teachers,id'
        ]);

        SchoolClass::create($validated);

        return redirect()->route('kelas.index')->with('success', 'Kelas berhasil ditambahkan.');
    }
}
