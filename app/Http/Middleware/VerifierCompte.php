<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifierCompte
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        // Compte désactivé pendant que la personne était connectée : on la déconnecte
        if ($user && !$user->actif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte est désactivé. Contacte le directeur.',
            ]);
        }

        // Mot de passe provisoire : tant qu'il n'est pas changé, seule la page « Mon compte » est accessible
        if ($user && $user->doit_changer_mot_de_passe && !$request->routeIs('compte.*', 'logout')) {
            return redirect()->route('compte.edit');
        }

        return $next($request);
    }
}
