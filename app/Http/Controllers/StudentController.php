<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\ClassStudent;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index()
    {
        $students = Student::with('schoolClasses.academicYear')->orderBy('name', 'asc')->get();
        $classes = SchoolClass::with('academicYear')->get();

        return view('pages.kesiswaan.siswa', [
            'title' => 'Data Siswa',
            'students' => $students,
            'classes' => $classes
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nis' => 'required|string|unique:students,nis',
            'nisn' => 'nullable|string|unique:students,nisn',
            'name' => 'required|string',
            'gender' => 'required|in:L,P',
            'phone' => 'nullable|string',
            'parent_name' => 'nullable|string',
            'parent_phone' => 'nullable|string',
            'school_class_id' => 'required|exists:school_classes,id'
        ]);

        $student = Student::create($request->only([
            'nis', 'nisn', 'name', 'gender', 'phone', 'parent_name', 'parent_phone'
        ]));

        // Assign to Class
        ClassStudent::create([
            'student_id' => $student->id,
            'school_class_id' => $request->school_class_id
        ]);

        return redirect()->route('siswa.index')->with('success', 'Siswa berhasil ditambahkan.');
    }
}
