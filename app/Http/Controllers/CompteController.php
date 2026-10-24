<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompteController extends Controller
{
    public function edit()
    {
        return view('compte.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $obligatoire = $user->doit_changer_mot_de_passe;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => [$obligatoire ? 'required' : 'nullable', 'confirmed', Password::min(8), 'different:password_actuel'],
        ], [
            'password.required' => 'Tu dois choisir un nouveau mot de passe pour continuer.',
            'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        ]);

        // Changer l'e-mail ou le mot de passe demande de confirmer l'ancien mot de passe
        $emailChange = mb_strtolower($request->email) !== mb_strtolower($user->email);
        if ($emailChange || $request->filled('password')) {
            $request->validate([
                'password_actuel' => ['required', 'current_password'],
            ], [
                'password_actuel.required' => 'Tape ton mot de passe actuel pour changer ton e-mail ou ton mot de passe.',
                'password_actuel.current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = $request->password;
            $user->doit_changer_mot_de_passe = false;
        }

        $user->save();

        if ($obligatoire && !$user->doit_changer_mot_de_passe) {
            return redirect()->route('home')->with('success', 'Mot de passe enregistré. Bienvenue !');
        }

        return redirect()->route('compte.edit')->with('success', 'Ton compte a été mis à jour.');
    }
}
