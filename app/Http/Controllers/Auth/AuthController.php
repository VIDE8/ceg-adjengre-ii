<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Un compte désactivé ne peut pas se connecter
        if (Auth::attempt($credentials + ['actif' => true])) {
            $request->session()->regenerate();

            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Mot de passe provisoire : on envoie d'abord vers « Mon compte »
            if ($user->doit_changer_mot_de_passe) {
                return redirect()->route('compte.edit');
            }

            if ($user->role === 'admin') return redirect()->intended('/');
            if ($user->role === 'comptable') return redirect()->intended('/paiements');
            if ($user->role === 'professeur') return redirect()->intended('/notes');
            if ($user->role === 'surveillant') return redirect()->intended('/retards');
        }

        return back()->withErrors([
            'email' => 'Identifiants incorrects ou compte désactivé.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
