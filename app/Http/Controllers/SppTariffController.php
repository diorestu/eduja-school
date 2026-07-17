<?php

namespace App\Http\Controllers;

use App\Models\SppTariff;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

class SppTariffController extends Controller
{
    public function index()
    {
        $tariffs = SppTariff::with(['schoolClass', 'academicYear'])->orderBy('created_at', 'desc')->get();
        $classes = SchoolClass::with('academicYear')->get();
        $academicYears = AcademicYear::all();

        return view('pages.keuangan.spp.tarif', [
            'title' => 'Tarif Biaya Sekolah (SPP)',
            'tariffs' => $tariffs,
            'classes' => $classes,
            'academicYears' => $academicYears
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:Bulanan,Sekali',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'academic_year_id' => 'required|exists:academic_years,id'
        ]);

        SppTariff::create($validated);

        return redirect()->route('spp.tarif.index')->with('success', 'Tarif biaya berhasil disimpan.');
    }
}
