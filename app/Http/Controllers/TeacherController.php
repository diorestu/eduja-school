<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::orderBy('name', 'asc')->get();

        return view('pages.kesiswaan.guru', [
            'title' => 'Data GTK (Guru & Staf)',
            'teachers' => $teachers
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nuptk' => 'nullable|string|unique:teachers,nuptk',
            'nip' => 'nullable|string|unique:teachers,nip',
            'name' => 'required|string',
            'role_type' => 'required|string',
            'staff_type' => 'nullable|string',
            'phone' => 'nullable|string',
        ]);

        Teacher::create($validated);

        return redirect()->route('guru.index')->with('success', 'GTK berhasil ditambahkan.');
    }
}
