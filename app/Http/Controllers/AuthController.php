<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->has('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
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
            'fname' => 'required|string|max:100',
            'lname' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['fname'] . ' ' . $validated['lname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'staf_tu' // Default role for self-registration
        ]);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Akun berhasil didaftarkan!');
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
