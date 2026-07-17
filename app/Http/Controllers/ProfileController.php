<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show the user profile form.
     */
    public function show()
    {
        return view('pages.profile', [
            'title' => 'Profil Pengguna',
            'user' => auth()->user()
        ]);
    }

    /**
     * Update the user profile.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'fname' => 'required|string|max:120',
            'lname' => 'required|string|max:120',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $user->name = trim($validated['fname'] . ' ' . $validated['lname']);
        $user->email = $validated['email'];

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', 'Profil Anda berhasil diperbarui!');
    }
}
