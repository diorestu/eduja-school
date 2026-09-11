<?php

namespace App\Http\Controllers;

use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolSelectionController extends Controller
{
    public function index(Request $request, SchoolContext $schoolContext): View|RedirectResponse
    {
        $schools = $schoolContext->availableSchools($request->user());

        if ($schools->count() === 1) {
            $request->session()->put('active_school_id', $schools->first()->id);

            return redirect()->intended(route('dashboard'));
        }

        return view('pages.school.select', [
            'title' => 'Pilih Sekolah',
            'schools' => $schools,
        ]);
    }

    public function switch(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer'],
        ]);

        $schoolContext->setActiveSchool($request->user(), (int) $validated['school_id']);

        return redirect()->intended(route('dashboard'))->with('success', 'Konteks sekolah aktif diperbarui.');
    }
}
