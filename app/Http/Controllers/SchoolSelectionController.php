<?php

namespace App\Http\Controllers;

use App\Services\RoleRedirectService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolSelectionController extends Controller
{
    public function index(Request $request, SchoolContext $schoolContext): View|RedirectResponse
    {
        if (in_array($request->user()->role, ['dinas', 'yayasan'], true)) {
            $request->session()->forget(['active_school_id', 'url.intended']);

            return redirect()->route(app(RoleRedirectService::class)->afterLogin($request->user()));
        }

        $schools = $schoolContext->availableSchools($request->user());
        if (! $schools->contains('id', $request->session()->get('active_school_id'))) {
            $request->session()->forget('active_school_id');
        }
        $request->session()->forget('url.intended');

        if ($schools->count() === 1) {
            $schoolContext->setActiveSchool($request->user(), $schools->first()->id);

            return redirect()->route('dashboard');
        }

        return view('pages.school.select', [
            'title' => $schools->isEmpty() ? 'Belum memiliki akses sekolah' : 'Pilih Sekolah',
            'schools' => $schools,
            'pendingSchoolNames' => $schools->isEmpty()
                ? $request->user()->schoolRoles()->where('membership_status', 'pending')->with('school:id,name')
                    ->get()->pluck('school.name')->filter()->unique()->values()
                : collect(),
        ]);
    }

    public function switch(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        if (in_array($request->user()->role, ['dinas', 'yayasan'], true)) {
            $request->session()->forget(['active_school_id', 'url.intended']);

            return redirect()->route(app(RoleRedirectService::class)->afterLogin($request->user()));
        }

        $validated = $request->validate([
            'school_id' => ['required', 'integer'],
        ]);

        $schoolContext->setActiveSchool($request->user(), (int) $validated['school_id']);
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')->with('success', 'Konteks sekolah aktif diperbarui.');
    }
}
