<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecapitulatifController extends Controller
{
    // Résultats d'une classe : réservés à l'administrateur et au professeur titulaire de la classe
    public function show(Request $request, Classe $classe, BulletinService $service)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole('admin') && $classe->professeur_id != $user->id) {
            abort(403, 'Seul le professeur titulaire de la classe peut consulter ses résultats.');
        }

        $trimestres = $service->trimestresActifs();
        $trimestre = $trimestres->firstWhere('id', (int) $request->trimestre_id)
            ?? $service->trimestreParDefaut($trimestres);

        $resultats = $trimestre ? $service->calculer($classe, $trimestre) : null;

        return view('recapitulatif.show', compact('classe', 'trimestres', 'trimestre', 'resultats'));
    }
}
