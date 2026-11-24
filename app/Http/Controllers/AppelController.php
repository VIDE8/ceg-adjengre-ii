<?php

namespace App\Http\Controllers;

use App\Models\AbsenceHeure;
use App\Models\Affectation;
use App\Models\Appel;
use App\Models\Classe;
use App\Models\Comportement;
use App\Models\Eleve;
use App\Services\BulletinService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AppelController extends Controller
{
    private const ORDRE_NIVEAUX = ['6ème' => 0, '5ème' => 1, '4ème' => 2, '3ème' => 3];

    private function nbCreneaux(): int
    {
        return (int) config('ecole.nombre_creneaux', 7);
    }

    // Appel de l'heure : le professeur choisit sa classe et sa matière, la date et l'heure de cours
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $affectations = Affectation::with(['classe', 'matiere'])
            ->when(!$user->hasRole('admin'), fn($q) => $q->where('professeur_id', $user->id))
            ->get()
            ->sortBy(fn($a) => (self::ORDRE_NIVEAUX[$a->classe->niveau] ?? 99) . '-' . $a->classe->nom_classe . '-' . $a->matiere->nom)
            ->values();

        $affectation = $affectations->firstWhere('id', (int) $request->affectation_id) ?? $affectations->first();
        $date = $request->filled('date') ? Carbon::parse($request->date)->toDateString() : today()->toDateString();
        $creneau = max(1, min($this->nbCreneaux(), (int) ($request->creneau ?: 1)));
        $duree = max(1, min(4, (int) ($request->duree ?: 1)));

        $eleves = collect();
        $appel = null;
        $statuts = [];
        $comportements = collect();
        $appelsDuJour = collect();

        if ($affectation) {
            $eleves = Eleve::where('classes_id', $affectation->classe_id)->orderBy('nom')->orderBy('prenom')->get();

            $appelsDuJour = Appel::with(['matiere', 'professeur'])
                ->where('classe_id', $affectation->classe_id)
                ->where('date', $date)
                ->orderBy('creneau')
                ->get();

            $appel = $appelsDuJour->firstWhere('creneau', $creneau);

            if ($appel) {
                $duree = $appel->duree;
                $statuts = AbsenceHeure::where('appel_id', $appel->id)->pluck('statut', 'eleve_id')->all();
                $comportements = Comportement::where('appel_id', $appel->id)->get()->keyBy('eleve_id');
            }
        }

        // Listes d'élèves de toutes les affectations du professeur : elles voyagent avec la page,
        // pour pouvoir changer de classe sans connexion (le directeur n'en a pas besoin)
        $rosters = [];
        if (!$user->hasRole('admin') && $affectations->isNotEmpty()) {
            $parClasse = Eleve::whereIn('classes_id', $affectations->pluck('classe_id')->unique())
                ->orderBy('nom')->orderBy('prenom')
                ->get(['id', 'classes_id', 'nom', 'prenom', 'matricule'])
                ->groupBy('classes_id');

            foreach ($affectations as $a) {
                $rosters[$a->id] = [
                    'classe' => $a->classe->niveau . ' ' . $a->classe->nom_classe . ' — ' . $a->matiere->nom,
                    'eleves' => ($parClasse[$a->classe_id] ?? collect())
                        ->map(fn($e) => ['id' => $e->id, 'nom' => $e->nom, 'prenom' => $e->prenom, 'matricule' => $e->matricule])
                        ->values()
                        ->all(),
                ];
            }
        }

        $nbCreneaux = $this->nbCreneaux();

        return view('appels.index', compact(
            'rosters',
            'affectations',
            'affectation',
            'date',
            'creneau',
            'duree',
            'eleves',
            'appel',
            'statuts',
            'comportements',
            'appelsDuJour',
            'nbCreneaux'
        ));
    }

    // Règles de validation de l'appel : les mêmes pour le formulaire et pour la synchronisation hors ligne
    public static function regles(): array
    {
        return [
            'affectation_id'              => 'required|exists:affectations,id',
            'date'                        => 'required|date|before_or_equal:today',
            'creneau'                     => 'required|integer|min:1|max:' . (int) config('ecole.nombre_creneaux', 7),
            'duree'                       => 'required|integer|min:1|max:4',
            'statuts'                     => 'nullable|array',
            'statuts.*'                   => 'in:present,absent,retard',
            'comportements'               => 'nullable|array',
            'comportements.*.type'        => 'nullable|in:incident,appreciation,sanction',
            'comportements.*.description' => 'nullable|string|max:500',
        ];
    }

    public static function messages(): array
    {
        return ['date.before_or_equal' => "On ne peut pas faire l'appel d'un jour à venir."];
    }

    public function enregistrer(Request $request)
    {
        $donnees = $request->validate(self::regles(), self::messages());

        $resultat = $this->appliquer(Auth::user(), $donnees);

        if ($resultat['erreurs']) {
            return back()->withInput()->withErrors($resultat['erreurs']);
        }

        return redirect()
            ->route('appels.index', [
                'affectation_id' => $resultat['affectation']->id,
                'date'           => $resultat['date'],
                'creneau'        => $resultat['creneau'],
                'duree'          => $resultat['duree'],
            ])
            ->with('success', $resultat['message']);
    }

    // Enregistre un appel déjà validé. Utilisée par le formulaire et par la synchronisation hors ligne.
    // Renvoie ['erreurs' => [...]] si l'appel est refusé, sinon le message de réussite.
    public function appliquer(\App\Models\User $user, array $donnees): array
    {
        $estAdmin = $user->hasRole('admin');

        $affectation = Affectation::findOrFail($donnees['affectation_id']);
        abort_unless($estAdmin || $affectation->professeur_id == $user->id, 403, "Cette matière ne t'est pas affectée dans cette classe.");

        $creneau = (int) $donnees['creneau'];
        $duree = (int) $donnees['duree'];
        $date = Carbon::parse($donnees['date'])->toDateString();
        $classeId = $affectation->classe_id;

        if ($creneau + $duree - 1 > $this->nbCreneaux()) {
            return ['erreurs' => ['duree' => "Un cours de {$duree} heure(s) à partir de l'heure {$creneau} dépasse la journée."]];
        }

        // Une heure de cours d'une classe n'a qu'un seul appel
        $appelsJour = Appel::with('professeur')->where('classe_id', $classeId)->where('date', $date)->get();
        $existant = $appelsJour->firstWhere('creneau', $creneau);

        if ($existant && !$estAdmin && $existant->professeur_id != $user->id) {
            return ['erreurs' => ['creneau' => 'Cet appel a déjà été fait par ' . ($existant->professeur->name ?? 'un autre professeur') . '.']];
        }

        foreach ($appelsJour as $autre) {
            if ($existant && $autre->id === $existant->id) {
                continue;
            }
            if ($autre->creneau < $creneau + $duree && $autre->creneau + $autre->duree > $creneau) {
                return ['erreurs' => ['creneau' => "Cette heure chevauche l'appel de l'heure {$autre->creneau} (" . ($autre->professeur->name ?? 'un professeur') . ').']];
            }
        }

        $eleves = Eleve::where('classes_id', $classeId)->get()->keyBy('id');

        // Comportement : le type et la description vont ensemble
        $erreurs = [];
        foreach ($eleves as $id => $eleve) {
            $type = data_get($donnees, "comportements.$id.type");
            $description = trim((string) data_get($donnees, "comportements.$id.description"));
            if (($type && $description === '') || (!$type && $description !== '')) {
                $erreurs[] = "Comportement de {$eleve->nom} {$eleve->prenom} : choisis le type et écris la description.";
            }
        }
        if ($erreurs) {
            return ['erreurs' => $erreurs];
        }

        $nbAbsents = 0;
        $nbRetards = 0;

        DB::transaction(function () use ($donnees, $affectation, $user, $date, $creneau, $duree, $classeId, $eleves, &$nbAbsents, &$nbRetards) {
            $appel = Appel::updateOrCreate(
                ['classe_id' => $classeId, 'date' => $date, 'creneau' => $creneau],
                ['matiere_id' => $affectation->matiere_id, 'professeur_id' => $affectation->professeur_id ?? $user->id, 'duree' => $duree]
            );

            $gardes = [];
            foreach (($donnees['statuts'] ?? []) as $eleveId => $statut) {
                if (!$eleves->has((int) $eleveId) || $statut === 'present') {
                    continue;
                }
                AbsenceHeure::updateOrCreate(
                    ['appel_id' => $appel->id, 'eleve_id' => (int) $eleveId],
                    ['statut' => $statut]
                );
                $gardes[] = (int) $eleveId;
                $statut === 'absent' ? $nbAbsents++ : $nbRetards++;
            }
            AbsenceHeure::where('appel_id', $appel->id)->whereNotIn('eleve_id', $gardes)->delete();

            foreach ($eleves as $id => $eleve) {
                $type = data_get($donnees, "comportements.$id.type");
                $description = trim((string) data_get($donnees, "comportements.$id.description"));
                $cle = ['appel_id' => $appel->id, 'eleve_id' => $id];

                if ($type && $description !== '') {
                    Comportement::updateOrCreate($cle, [
                        'classe_id' => $classeId,
                        'date' => $date,
                        'type' => $type,
                        'description' => $description,
                        'enregistre_par' => $user->id,
                    ]);
                } else {
                    Comportement::where($cle)->delete();
                }
            }
        });

        return [
            'erreurs'     => [],
            'message'     => "Appel enregistré : {$nbAbsents} absent(s), {$nbRetards} retard(s).",
            'affectation' => $affectation,
            'date'        => $date,
            'creneau'     => $creneau,
            'duree'       => $duree,
        ];
    }

    // Absences de la classe sur un trimestre : heures, jours, retards, et justification
    public function synthese(Request $request, Classe $classe, BulletinService $service)
    {
        $this->autoriser($classe);

        $trimestres = $service->trimestresActifs();
        $trimestre = $trimestres->firstWhere('id', (int) $request->trimestre_id)
            ?? $service->trimestreParDefaut($trimestres);

        $eleves = Eleve::where('classes_id', $classe->id)->orderBy('nom')->orderBy('prenom')->get();

        $lignes = $trimestre
            ? $service->absencesDetaillees($eleves, $trimestre)->sortByDesc(fn($l) => $l->appel->date->toDateString() . $l->appel->creneau)->values()
            : collect();

        $parEleve = $lignes->groupBy('eleve_id');

        return view('appels.synthese', compact('classe', 'trimestres', 'trimestre', 'eleves', 'lignes', 'parEleve'));
    }

    // Marque une absence comme justifiée (ou annule la justification)
    public function basculerJustification(AbsenceHeure $absence)
    {
        $absence->load('appel.classe');
        $this->autoriser($absence->appel->classe);

        $absence->justifie = !$absence->justifie;
        $absence->save();

        return back()->with('success', $absence->justifie ? 'Absence justifiée.' : 'Justification retirée.');
    }

    // Réservé à l'administrateur et au professeur titulaire de la classe
    private function autoriser(Classe $classe): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole('admin') && $classe->professeur_id != $user->id) {
            abort(403, 'Seul le professeur titulaire de la classe peut consulter ses absences.');
        }
    }
}

