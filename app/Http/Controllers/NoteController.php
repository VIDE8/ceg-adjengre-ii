<?php

namespace App\Http\Controllers;

use App\Models\Affectation;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Trimestre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NoteController extends Controller
{
    private const ORDRE_NIVEAUX = ['6ème' => 0, '5ème' => 1, '4ème' => 2, '3ème' => 3];

    // Nombre maximum de notes de classe (devoirs) par matière et par trimestre
    private const MAX_DEVOIRS = 6;

    // Accueil du professeur : ses classes et ses matières (d'après les affectations)
    public function index()
    {
        $affectations = Affectation::with(['classe', 'matiere'])
            ->where('professeur_id', Auth::id())
            ->get()
            ->sortBy(fn($a) => (self::ORDRE_NIVEAUX[$a->classe->niveau] ?? 99)
                . '-' . $a->classe->nom_classe . '-' . $a->matiere->nom)
            ->values();

        return view('notes.index', compact('affectations'));
    }

    // Grille de saisie : tous les élèves de la classe pour une matière et un trimestre
    public function saisie(Request $request)
    {
        $request->validate([
            'classe_id'  => 'required|exists:classes,id',
            'matiere_id' => 'required|exists:matieres,id',
        ]);

        $affectation = $this->affectationDuProfesseur($request->classe_id, $request->matiere_id);

        $trimestres = $this->trimestresActifs();
        $trimestre = $trimestres->firstWhere('id', (int) $request->trimestre_id)
            ?? $this->trimestreParDefaut($trimestres);

        $eleves = Eleve::where('classes_id', $affectation->classe_id)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $notes = $trimestre
            ? Note::where('matiere_id', $affectation->matiere_id)
            ->where('trimestre_id', $trimestre->id)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->get()
            : collect();

        // $saisies[eleve_id] = ['devoir' => [1 => 12.5, 2 => 11], 'composition' => 10]
        $saisies = [];
        foreach ($notes as $note) {
            if ($note->type_evaluation === 'composition') {
                $saisies[$note->eleve_id]['composition'] = $note->valeur;
            } else {
                $saisies[$note->eleve_id]['devoir'][$note->ordre] = $note->valeur;
            }
        }

        $nbDevoirs = max(2, (int) ($notes->where('type_evaluation', 'devoir')->max('ordre') ?? 0));
        $maxDevoirs = self::MAX_DEVOIRS;

        return view('notes.saisie', compact(
            'affectation',
            'trimestres',
            'trimestre',
            'eleves',
            'saisies',
            'nbDevoirs',
            'maxDevoirs'
        ));
    }

    // Enregistre toute la grille d'un coup
    public function enregistrer(Request $request)
    {
        // Accepte la virgule comme séparateur décimal (12,5)
        $request->merge(['notes' => $this->normaliser($request->input('notes', []))]);

        $request->validate([
            'classe_id'            => 'required|exists:classes,id',
            'matiere_id'           => 'required|exists:matieres,id',
            'trimestre_id'         => 'required|exists:trimestres,id',
            'notes'                => 'nullable|array',
            'notes.*.devoir.*'     => 'nullable|numeric|between:0,20',
            'notes.*.composition'  => 'nullable|numeric|between:0,20',
        ], [
            'notes.*.devoir.*.numeric'    => 'Chaque note doit être un nombre.',
            'notes.*.devoir.*.between'    => 'Chaque note doit être comprise entre 0 et 20.',
            'notes.*.composition.numeric' => 'Chaque note doit être un nombre.',
            'notes.*.composition.between' => 'Chaque note doit être comprise entre 0 et 20.',
        ]);

        $affectation = $this->affectationDuProfesseur($request->classe_id, $request->matiere_id);

        $trimestre = $this->trimestresActifs()->firstWhere('id', (int) $request->trimestre_id);
        abort_if(!$trimestre, 422, "Ce trimestre n'appartient pas à l'année scolaire active.");

        $eleveIds = Eleve::where('classes_id', $affectation->classe_id)->pluck('id')->all();
        $userId = Auth::id();

        DB::transaction(function () use ($request, $affectation, $trimestre, $eleveIds, $userId) {
            foreach ($request->input('notes', []) as $eleveId => $saisie) {
                // On ignore tout élève qui n'est pas dans cette classe
                if (!in_array((int) $eleveId, $eleveIds, true)) {
                    continue;
                }

                $creneaux = [];

                foreach (($saisie['devoir'] ?? []) as $ordre => $valeur) {
                    $ordre = (int) $ordre;
                    if ($ordre >= 1 && $ordre <= self::MAX_DEVOIRS) {
                        $creneaux[] = ['devoir', $ordre, $valeur];
                    }
                }

                if (array_key_exists('composition', $saisie)) {
                    $creneaux[] = ['composition', 1, $saisie['composition']];
                }

                foreach ($creneaux as [$type, $ordre, $valeur]) {
                    $cle = [
                        'eleve_id'        => (int) $eleveId,
                        'matiere_id'      => $affectation->matiere_id,
                        'trimestre_id'    => $trimestre->id,
                        'type_evaluation' => $type,
                        'ordre'           => $ordre,
                    ];

                    if ($valeur === null || $valeur === '') {
                        // Case vidée : on supprime la note
                        Note::where($cle)->delete();
                    } else {
                        Note::updateOrCreate($cle, ['valeur' => $valeur, 'saisi_par' => $userId]);
                    }
                }
            }
        });

        return redirect()
            ->route('notes.saisie', [
                'classe_id'    => $affectation->classe_id,
                'matiere_id'   => $affectation->matiere_id,
                'trimestre_id' => $trimestre->id,
            ])
            ->with('success', 'Notes enregistrées avec succès !');
    }

    // Le professeur ne peut saisir que les matières qui lui sont affectées dans la classe
    private function affectationDuProfesseur($classeId, $matiereId): Affectation
    {
        $affectation = Affectation::with(['classe', 'matiere'])
            ->where('classe_id', $classeId)
            ->where('matiere_id', $matiereId)
            ->where('professeur_id', Auth::id())
            ->first();

        abort_if(!$affectation, 403, "Cette matière ne vous est pas affectée dans cette classe.");

        return $affectation;
    }

    // Trimestres de l'année scolaire active
    private function trimestresActifs()
    {
        return Trimestre::with('anneeScolaire')
            ->whereHas('anneeScolaire', fn($q) => $q->where('active', true))
            ->orderBy('numero')
            ->get();
    }

    // Trimestre en cours aujourd'hui, sinon le dernier commencé, sinon le premier
    private function trimestreParDefaut($trimestres)
    {
        $enCours = $trimestres->first(
            fn($t) => $t->date_debut->lte(now()) && $t->date_fin->gte(now()->startOfDay())
        );

        if ($enCours) {
            return $enCours;
        }

        $commences = $trimestres->filter(fn($t) => $t->date_debut->lte(now()));

        return $commences->last() ?? $trimestres->first();
    }

    private function normaliser($valeur)
    {
        if (is_array($valeur)) {
            return array_map(fn($v) => $this->normaliser($v), $valeur);
        }

        return is_string($valeur) ? str_replace(',', '.', trim($valeur)) : $valeur;
    }
}
