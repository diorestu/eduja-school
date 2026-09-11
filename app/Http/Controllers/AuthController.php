<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;

class AuthController extends Controller
{
    /**
     * Show the sign-in form.
     */
    public function showSignin()
    {
        return view('pages.auth.signin');
    }

    /**
     * Handle sign-in request.
     */
    public function signin(Request $request)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim($credentials['login']);
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', strtolower($login))->first()
            : User::where('phone_normalized', $this->normalizePhone($login))->first();

        if ($user && Hash::check($credentials['password'], $user->password) && $user->onboarding_status === 'active' && (! in_array($user->role, ['guru'], true) || filled($user->email_verified_at))) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return $this->redirectAfterLogin($request, $user);
        }

        return back()->withErrors([
            'email' => 'Email/nomor HP atau password tidak valid, atau akun belum aktif.',
        ])->onlyInput('login');
    }

    private function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $route = app(\App\Services\RoleRedirectService::class)->afterLogin($user);
        if ($route === 'dashboard') {
            $school = app(\App\Services\SchoolContext::class)->availableSchools($user)->first();
            if ($school) $request->session()->put('active_school_id', $school->id);
        }

        return redirect()->intended(route($route));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0')) return '62'.substr($digits, 1);
        return $digits;
    }

    /**
     * Show the sign-up form.
     */
    public function showSignup()
    {
        return view('pages.auth.signup');
    }

    /**
     * Handle sign-up request.
     */
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'registration_type' => 'required|in:guru,wali_murid,sekolah',
            'fname' => 'required|string|max:100', 'lname' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:30',
            'school_code' => 'nullable|string|max:50', 'school_name' => 'required_if:registration_type,sekolah|string|max:150',
        ]);
        $type = $validated['registration_type'];
        $role = ['guru' => 'guru', 'wali_murid' => 'orang_tua', 'sekolah' => 'pic_sekolah'][$type];
        $school = null;
        if (in_array($type, ['guru', 'wali_murid'], true) && ! empty($validated['school_code'])) {
            $school = School::where('registration_code', $validated['school_code'])->where('status', 'active')->first();
            if (! $school) return back()->withErrors(['school_code' => 'Kode sekolah tidak valid atau sekolah belum aktif.'])->withInput();
        }
        $user = DB::transaction(function () use ($validated, $type, $role, $school) {
            $user = User::create(['name' => $validated['fname'].' '.$validated['lname'], 'email' => $validated['email'], 'phone' => $validated['phone'] ?? null, 'phone_normalized' => filled($validated['phone'] ?? null) ? $this->normalizePhone($validated['phone']) : null, 'password' => Hash::make($validated['password']), 'role' => $role, 'registration_type' => $type, 'onboarding_status' => $type === 'sekolah' ? 'pending' : 'active']);
            if ($type === 'sekolah') $school = School::create(['name' => $validated['school_name'], 'status' => 'pending', 'is_active' => false, 'registration_code' => strtoupper('EDUJA-'.substr(bin2hex(random_bytes(4)), 0, 8))]);
            if ($school) $school->roles()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => $type !== 'sekolah', 'membership_status' => $type === 'sekolah' ? 'pending' : 'active']);
            return $user;
        });
        if ($type === 'wali_murid') Auth::login($user);
        return $type === 'wali_murid' ? redirect()->route('dashboard')->with('success', 'Akun wali murid berhasil didaftarkan!') : redirect()->route('login')->with('success', $type === 'sekolah' ? 'Pendaftaran sekolah menunggu verifikasi superadmin.' : 'Silakan verifikasi email sebelum masuk.');
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
