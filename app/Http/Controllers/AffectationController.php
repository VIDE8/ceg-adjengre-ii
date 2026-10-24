<?php

namespace App\Http\Controllers;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AffectationController extends Controller
{
    // Ordre d'affichage des niveaux du collège
    private const ORDRE_NIVEAUX = ['6ème' => 0, '5ème' => 1, '4ème' => 2, '3ème' => 3];

    public function index(Request $request)
    {
        $classes = Classe::with('professeur')->get()
            ->sortBy(fn($c) => (self::ORDRE_NIVEAUX[$c->niveau] ?? 99) . '-' . $c->nom_classe)
            ->values();

        $classe = $request->filled('classe_id')
            ? $classes->firstWhere('id', (int) $request->classe_id)
            : null;
        $classe = $classe ?? $classes->first();

        $matieres = Matiere::orderBy('nom')->get();

        // Uniquement les utilisateurs qui ont le rôle professeur
        $professeurs = User::where('role', 'professeur')->orderBy('name')->get();

        $affectations = $classe
            ? Affectation::with(['matiere', 'professeur'])
            ->where('classe_id', $classe->id)
            ->get()
            ->sortBy(fn($a) => $a->matiere->nom)
            ->values()
            : collect();

        return view('affectations.index', compact('classes', 'classe', 'matieres', 'professeurs', 'affectations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'classe_id'     => 'required|exists:classes,id',
            'matiere_id'    => 'required|exists:matieres,id',
            'professeur_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'professeur')],
            'coefficient'   => 'required|integer|min:1|max:10',
        ]);

        // Une matière n'a qu'un professeur par classe ; un professeur peut
        // en revanche enseigner plusieurs matières.
        Affectation::updateOrCreate(
            ['classe_id' => $data['classe_id'], 'matiere_id' => $data['matiere_id']],
            ['professeur_id' => $data['professeur_id'] ?? null, 'coefficient' => $data['coefficient']]
        );

        return redirect()
            ->route('affectations.index', ['classe_id' => $data['classe_id']])
            ->with('success', 'Affectation enregistrée.');
    }

    public function destroy(Affectation $affectation)
    {
        $classeId = $affectation->classe_id;
        $affectation->delete();

        return redirect()
            ->route('affectations.index', ['classe_id' => $classeId])
            ->with('success', 'Affectation supprimée.');
    }
}
