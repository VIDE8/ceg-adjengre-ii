<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Eleve;
use App\Services\EcolageService;
use Illuminate\Http\Request;

/**
 * Contrôleur API (JSON) pour le module Élèves.
 * Consommé par le front-end React (resources/js/react).
 * N'affecte pas les routes/vues Blade existantes (module 100% additif).
 */
class EleveApiController extends Controller
{
    // Début des matricules (ex. 2026-EP-001). Une seule ligne à changer si besoin.
    private const PREFIXE_MATRICULE = '2026-EP-';

    // Ordre d'affichage des niveaux du collège
    private const ORDRE_NIVEAUX = ['6ème' => 0, '5ème' => 1, '4ème' => 2, '3ème' => 3];

    /**
     * Liste des élèves (avec filtres optionnels) + classes + prochain matricule.
     * GET /api/eleves
     */
    public function index(Request $request, EcolageService $ecolage)
    {
        $query = Eleve::with(['classe', 'paiements']);

        if ($request->filled('classe_id')) {
            $query->where('classes_id', $request->classe_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'LIKE', "%{$search}%")
                    ->orWhere('prenom', 'LIKE', "%{$search}%")
                    ->orWhere('matricule', 'LIKE', "%{$search}%");
            });
        }

        $eleves = $query->orderBy('nom')->orderBy('prenom')->get();

        // Situation d'écolage de chaque élève (écolage de sa classe pour l'année active)
        $situations = $ecolage->situations($eleves);
        $eleves->each(fn($e) => $e->setAttribute('ecolage', $situations[$e->id]));

        $prochainMatricule = self::prochainMatricule();

        // Classes triées 6ème, 5ème, 4ème, 3ème puis par nom
        $classes = Classe::all()
            ->sortBy(fn($c) => (self::ORDRE_NIVEAUX[$c->niveau] ?? 99) . '-' . $c->nom_classe)
            ->values();

        return response()->json([
            'eleves' => $eleves,
            'classes' => $classes,
            'prochain_matricule' => $prochainMatricule,
        ]);
    }

    /**
     * Prochain matricule : le plus grand numéro existant + 1.
     * Utilisé aussi par la synchronisation hors ligne, qui attribue le matricule à l'arrivée de l'élève.
     */
    public static function prochainMatricule(): string
    {
        $prefixe = self::PREFIXE_MATRICULE;
        $debutNumero = strlen($prefixe) + 1;

        $dernierNumero = Eleve::where('matricule', 'LIKE', $prefixe . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(matricule, {$debutNumero}) AS UNSIGNED)) as max_num")
            ->value('max_num');

        return $prefixe . str_pad(($dernierNumero ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Création d'un élève.
     * POST /api/eleves
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'sexe' => 'required|in:M,F',
            'date_naissance' => 'required|date',
            'classes_id' => 'required|exists:classes,id',
            'matricule' => 'required|string|unique:eleves,matricule',
            'statut' => 'nullable|string|max:10',
        ]);

        $eleve = Eleve::create($validated);
        $eleve->load('classe');

        return response()->json([
            'message' => "Élève inscrit avec succès !",
            'eleve' => $eleve,
        ], 201);
    }

    /**
     * Modification d'un élève.
     * PUT /api/eleves/{id}
     */
    public function update(Request $request, $id)
    {
        $eleve = Eleve::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'sexe' => 'required|in:M,F',
            'date_naissance' => 'required|date',
            'classes_id' => 'required|exists:classes,id',
            'statut' => 'nullable|string|max:10',
        ]);

        $eleve->update($validated);
        $eleve->load('classe');

        return response()->json([
            'message' => "Informations de l'élève mises à jour avec succès !",
            'eleve' => $eleve,
        ]);
    }

    /**
     * Suppression d'un élève (et de ses notes/paiements liés).
     * DELETE /api/eleves/{id}
     */
    public function destroy($id)
    {
        $eleve = Eleve::findOrFail($id);

        $eleve->paiements()->delete();
        $eleve->notes()->delete();
        $eleve->delete();

        return response()->json([
            'message' => "L'élève a été retiré du registre avec succès.",
        ]);
    }
}
