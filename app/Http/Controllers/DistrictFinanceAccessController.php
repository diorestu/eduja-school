<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DistrictFinanceAccessController extends Controller
{
    public function index(Request $request, SchoolContext $context): View
    {
        return view('pages.settings.dinas-access', [
            'title' => 'Izin Keuangan Dinas',
            'school' => School::findOrFail($context->activeSchoolIdFor($request->user())),
        ]);
    }

    public function update(Request $request, SchoolContext $context): RedirectResponse
    {
        $validated = $request->validate(['dinas_finance_allowed' => ['required', 'boolean']]);
        $school = School::findOrFail($context->activeSchoolIdFor($request->user()));
        $school->dinas_finance_allowed = $validated['dinas_finance_allowed'];
        $school->save();

        return redirect()->route('settings.dinas-access.index')->with('success',
            $school->dinas_finance_allowed ? 'Izin melihat keuangan untuk Dinas Pendidikan telah diberikan.' : 'Izin melihat keuangan untuk Dinas Pendidikan telah dicabut.');
    }
}
