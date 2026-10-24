<?php

namespace App\Http\Controllers;

use App\Models\AnneeScolaire;
use App\Models\Note;
use App\Models\Trimestre;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrimestreController extends Controller
{
    public function index()
    {
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();

        $trimestres = Trimestre::with('anneeScolaire')
            ->orderByDesc('annee_scolaire_id')
            ->orderBy('numero')
            ->get();

        return view('trimestres.index', compact('anneesScolaires', 'trimestres'));
    }

    public function store(Request $request)
    {
        Trimestre::create($this->valider($request));

        return redirect()->route('trimestres.index')->with('success', 'Trimestre enregistré avec succès !');
    }

    public function edit(Trimestre $trimestre)
    {
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();

        return view('trimestres.edit', compact('trimestre', 'anneesScolaires'));
    }

    public function update(Request $request, Trimestre $trimestre)
    {
        $trimestre->update($this->valider($request, $trimestre));

        return redirect()->route('trimestres.index')->with('success', 'Trimestre modifié avec succès !');
    }

    public function destroy(Trimestre $trimestre)
    {
        // On ne supprime jamais un trimestre qui contient déjà des notes
        if (Note::where('trimestre_id', $trimestre->id)->exists()) {
            return redirect()->route('trimestres.index')->withErrors([
                'suppression' => 'Suppression impossible : des notes sont déjà saisies dans ce trimestre.',
            ]);
        }

        $trimestre->delete();

        return redirect()->route('trimestres.index')->with('success', 'Trimestre supprimé.');
    }

    private function valider(Request $request, ?Trimestre $trimestre = null): array
    {
        return $request->validate([
            'annee_scolaire_id' => ['required', Rule::exists(AnneeScolaire::class, 'id')],
            'numero' => [
                'required',
                'integer',
                'between:1,3',
                // Un seul 1er, 2ème et 3ème trimestre par année scolaire
                Rule::unique(Trimestre::class, 'numero')
                    ->where('annee_scolaire_id', $request->annee_scolaire_id)
                    ->ignore($trimestre?->id),
            ],
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ], [
            'numero.unique' => 'Ce trimestre existe déjà pour cette année scolaire.',
            'date_fin.after_or_equal' => 'La date de fin doit être après la date de début.',
        ]);
    }
}
