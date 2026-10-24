<?php

namespace App\Http\Controllers;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\TarifEcolage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EcolageController extends Controller
{
    private const ORDRE_NIVEAUX = ['6ème' => 0, '5ème' => 1, '4ème' => 2, '3ème' => 3];

    // Écolage de base de chaque classe pour une année scolaire
    public function index(Request $request)
    {
        $annees = AnneeScolaire::orderByDesc('date_debut')->get();

        $annee = $annees->firstWhere('id', (int) $request->annee_id)
            ?? $annees->firstWhere('active', true)
            ?? $annees->first();

        $classes = Classe::all()
            ->sortBy(fn($c) => (self::ORDRE_NIVEAUX[$c->niveau] ?? 99) . '-' . $c->nom_classe)
            ->values();

        $tarifs = $annee
            ? TarifEcolage::where('annee_scolaire_id', $annee->id)->pluck('montant', 'classe_id')
            : collect();

        // Reprise des montants d'une autre année (proposés dans les cases, sans rien enregistrer)
        $reprise = null;
        $montantsReprise = collect();
        if ($annee && $request->filled('reprendre')) {
            $reprise = $annees->firstWhere('id', (int) $request->reprendre);
            if ($reprise && $reprise->id !== $annee->id) {
                $montantsReprise = TarifEcolage::where('annee_scolaire_id', $reprise->id)->pluck('montant', 'classe_id');
            } else {
                $reprise = null;
            }
        }

        $effectifs = Eleve::selectRaw('classes_id, COUNT(*) as total')->groupBy('classes_id')->pluck('total', 'classes_id');

        return view('ecolage.index', compact('annees', 'annee', 'classes', 'tarifs', 'reprise', 'montantsReprise', 'effectifs'));
    }

    public function enregistrer(Request $request)
    {
        // Accepte « 25 000 » ou « 25000 » : on ne garde que les chiffres
        $montants = collect($request->input('montants', []))
            ->map(fn($m) => is_string($m) ? preg_replace('/\D/', '', $m) : $m)
            ->all();
        $request->merge(['montants' => $montants]);

        $request->validate([
            'annee_scolaire_id' => ['required', Rule::exists(AnneeScolaire::class, 'id')],
            'montants'          => 'nullable|array',
            'montants.*'        => 'nullable|integer|min:0|max:10000000',
        ], [
            'montants.*.integer' => 'Chaque montant doit être un nombre entier de francs.',
            'montants.*.max'     => 'Un montant ne peut pas dépasser 10 000 000 F.',
        ]);

        $anneeId = (int) $request->annee_scolaire_id;
        $classeIds = Classe::pluck('id')->all();

        DB::transaction(function () use ($montants, $anneeId, $classeIds) {
            foreach ($montants as $classeId => $montant) {
                if (!in_array((int) $classeId, $classeIds)) {
                    continue;
                }

                $cle = ['annee_scolaire_id' => $anneeId, 'classe_id' => (int) $classeId];

                if ($montant === null || $montant === '') {
                    TarifEcolage::where($cle)->delete(); // case vidée : écolage non défini
                } else {
                    TarifEcolage::updateOrCreate($cle, ['montant' => (int) $montant]);
                }
            }
        });

        return redirect()
            ->route('ecolage.index', ['annee_id' => $anneeId])
            ->with('success', 'Écolage enregistré avec succès !');
    }
}
