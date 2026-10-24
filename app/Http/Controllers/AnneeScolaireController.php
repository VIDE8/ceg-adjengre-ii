<?php

namespace App\Http\Controllers;

use App\Models\AnneeScolaire;
use App\Models\Trimestre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnneeScolaireController extends Controller
{
    public function index()
    {
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();

        return view('annees_scolaires.index', compact('anneesScolaires'));
    }

    public function store(Request $request)
    {
        $data = $this->valider($request);
        $data['active'] = $request->boolean('active');

        DB::transaction(function () use ($data) {
            // Une seule année active à la fois
            if ($data['active']) {
                AnneeScolaire::query()->update(['active' => false]);
            }
            AnneeScolaire::create($data);
        });

        return redirect()->route('annees_scolaires.index')->with('success', 'Année scolaire enregistrée avec succès !');
    }

    public function activer($id)
    {
        DB::transaction(function () use ($id) {
            AnneeScolaire::query()->update(['active' => false]);
            AnneeScolaire::findOrFail($id)->update(['active' => true]);
        });

        return redirect()->route('annees_scolaires.index')->with('success', 'Année scolaire activée.');
    }

    public function edit(AnneeScolaire $annee)
    {
        return view('annees_scolaires.edit', compact('annee'));
    }

    public function update(Request $request, AnneeScolaire $annee)
    {
        $data = $this->valider($request, $annee);

        // Une année déjà active le reste ; pour en changer, on en active une autre depuis la liste
        $data['active'] = $annee->active || $request->boolean('active');

        DB::transaction(function () use ($data, $annee) {
            if ($data['active'] && !$annee->active) {
                AnneeScolaire::query()->update(['active' => false]);
            }
            $annee->update($data);
        });

        return redirect()->route('annees_scolaires.index')->with('success', 'Année scolaire modifiée avec succès !');
    }

    public function destroy(AnneeScolaire $annee)
    {
        if ($annee->active) {
            return redirect()->route('annees_scolaires.index')->withErrors([
                'suppression' => "Suppression impossible : c'est l'année active. Active d'abord une autre année.",
            ]);
        }

        if (Trimestre::where('annee_scolaire_id', $annee->id)->exists()) {
            return redirect()->route('annees_scolaires.index')->withErrors([
                'suppression' => 'Suppression impossible : cette année a des trimestres. Supprime-les d\'abord.',
            ]);
        }

        $annee->delete();

        return redirect()->route('annees_scolaires.index')->with('success', 'Année scolaire supprimée.');
    }

    private function valider(Request $request, ?AnneeScolaire $annee = null): array
    {
        return $request->validate([
            'libelle' => [
                'required',
                'string',
                'max:50',
                Rule::unique(AnneeScolaire::class, 'libelle')->ignore($annee?->id),
            ],
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
        ], [
            'libelle.unique' => 'Cette année scolaire existe déjà.',
            'date_fin.after' => 'La date de fin doit être après la date de début.',
        ]);
    }
}
