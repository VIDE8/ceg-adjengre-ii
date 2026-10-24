<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Permission;
use App\Models\AbsenceHeure;
use App\Models\Trimestre;
use Illuminate\Support\Collection;

/**
 * Calculs du bulletin : moyennes par matière, moyenne générale pondérée,
 * rangs (avec ex æquo), moyennes Littérature / Science, moyenne annuelle.
 *
 * Règles (d'après le bulletin du CEG ADJENGRE II) :
 *  - moyenne d'une matière = (moyenne des notes de classe + composition) ÷ 2
 *  - note définitive = moyenne de la matière × coefficient
 *  - moyenne du trimestre = total des notes définitives ÷ total des coefficients
 *  - moyenne annuelle = moyenne des moyennes des trimestres
 *  - une note manquante (composition ou notes de classe) compte 0
 *  - une matière où personne n'a encore de note n'est pas comptée
 */
class BulletinService
{
    /** Trimestres de l'année scolaire active. */
    public function trimestresActifs(): Collection
    {
        return Trimestre::with('anneeScolaire')
            ->whereHas('anneeScolaire', fn($q) => $q->where('active', true))
            ->orderBy('numero')
            ->get();
    }

    /** Trimestre en cours aujourd'hui, sinon le dernier commencé, sinon le premier. */
    public function trimestreParDefaut(Collection $trimestres): ?Trimestre
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

    /** Heures d'absence en jours : 17 h devient « 17 h (2 j 3 h) » (7 h = 1 jour). */
    public static function formaterHeures(int $heures): string
    {
        if ($heures <= 0) {
            return '';
        }

        $parJour = max(1, (int) config('ecole.heures_par_jour', 7));
        $jours = intdiv($heures, $parJour);
        $reste = $heures % $parJour;
        $texte = $heures . ' h';

        if ($jours > 0) {
            $texte .= ' (' . $jours . ' j' . ($reste > 0 ? ' ' . $reste . ' h' : '') . ')';
        }

        return $texte;
    }

    /** Absences et retards de plusieurs élèves sur un trimestre, avec la justification effective. */
    public function absencesDetaillees(Collection $eleves, Trimestre $trimestre): Collection
    {
        $debut = $trimestre->date_debut->toDateString();
        $fin = $trimestre->date_fin->toDateString();

        $lignes = AbsenceHeure::with(['appel.matiere', 'appel.professeur', 'eleve'])
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->whereHas('appel', fn($q) => $q->whereBetween('date', [$debut, $fin]))
            ->get();

        // Une permission d'absence justifie toutes les heures d'absence de sa période
        $permissions = Permission::where('type', 'absence')
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->where('date_debut', '<=', $fin)
            ->where('date_fin', '>=', $debut)
            ->get()
            ->groupBy('eleve_id');

        return $lignes->each(function ($ligne) use ($permissions) {
            $jour = $ligne->appel->date->toDateString();
            $couverte = $permissions->get($ligne->eleve_id, collect())->contains(
                fn($p) => $p->date_debut->toDateString() <= $jour && $p->date_fin->toDateString() >= $jour
            );
            $ligne->setAttribute('justifie_effectif', $ligne->justifie || $couverte);
        });
    }

    /** Appréciation d'après la moyenne sur 20. */
    public static function appreciation(float $moyenne): string
    {
        return match (true) {
            $moyenne >= 16 => 'Très bien',
            $moyenne >= 14 => 'Bien',
            $moyenne >= 12 => 'Assez bien',
            $moyenne >= 10 => 'Passable',
            $moyenne >= 8  => 'Insuffisant',
            default        => 'Très insuffisant',
        };
    }

    /** Observation proposée au conseil de classe d'après la moyenne (modifiable). */
    public static function observation(float $moyenne): string
    {
        return match (true) {
            $moyenne >= 16 => 'Très bon travail',
            $moyenne >= 14 => 'Bon travail',
            $moyenne >= 12 => 'Assez bon travail',
            $moyenne >= 10 => 'Travail passable',
            $moyenne >= 8  => 'Travail insuffisant',
            default        => 'Travail très insuffisant',
        };
    }

    /**
     * Résultats complets d'une classe pour un trimestre,
     * avec l'historique des trimestres et la moyenne annuelle.
     */
    public function calculer(Classe $classe, Trimestre $trimestre): array
    {
        $resultats = $this->calculerTrimestre($classe, $trimestre);

        // Historique : moyenne et rang de chaque élève, trimestre par trimestre
        $historique = [];
        $trimestresAnnee = Trimestre::where('annee_scolaire_id', $trimestre->annee_scolaire_id)
            ->orderBy('numero')
            ->get();

        foreach ($trimestresAnnee as $t) {
            $r = $t->id === $trimestre->id ? $resultats : $this->calculerTrimestre($classe, $t);

            if (empty($r['matieres_actives'])) {
                continue; // trimestre sans aucune note : ignoré
            }

            foreach ($r['eleves'] as $id => $e) {
                $historique[$id][$t->numero] = [
                    'moyenne' => $e['moyenne'],
                    'rang'    => $e['rang'],
                    'ex'      => $e['ex'],
                ];
            }
        }

        // Moyenne annuelle = moyenne des moyennes des trimestres, puis rang annuel
        $annuelles = [];
        foreach ($resultats['eleves'] as $id => $e) {
            $moyennes = collect($historique[$id] ?? [])->pluck('moyenne')->filter(fn($m) => $m !== null);
            $annuelles[$id] = $moyennes->isNotEmpty() ? round($moyennes->avg(), 2) : null;
        }

        $rangsAnnuels = $this->classer(array_filter($annuelles, fn($m) => $m !== null));

        foreach ($resultats['eleves'] as $id => &$e) {
            $e['historique'] = $historique[$id] ?? [];
            $e['annuel'] = [
                'moyenne' => $annuelles[$id],
                'rang'    => $rangsAnnuels[$id]['rang'] ?? null,
                'ex'      => $rangsAnnuels[$id]['ex'] ?? false,
            ];
        }
        unset($e);

        $valeursAnnuelles = array_filter($annuelles, fn($m) => $m !== null);
        $resultats['stats']['moyenne_classe_annuelle'] = $valeursAnnuelles
            ? round(array_sum($valeursAnnuelles) / count($valeursAnnuelles), 2)
            : null;

        return $resultats;
    }

    /** Résultats d'une classe pour un seul trimestre. */
    public function calculerTrimestre(Classe $classe, Trimestre $trimestre): array
    {
        $affectations = Affectation::with(['matiere', 'professeur'])
            ->where('classe_id', $classe->id)
            ->get()
            ->sortBy('matiere_id')
            ->values();

        $eleves = Eleve::where('classes_id', $classe->id)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $notes = Note::where('trimestre_id', $trimestre->id)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->whereIn('matiere_id', $affectations->pluck('matiere_id'))
            ->get();

        // Matières où au moins un élève a une note : seules celles-là comptent
        $actives = $notes->pluck('matiere_id')->map(fn($id) => (int) $id)->unique()->values()->all();
        $parCle = $notes->groupBy(fn($n) => $n->matiere_id . '-' . $n->eleve_id);

        // Absences et retards enregistrés à l'appel de chaque heure, pendant le trimestre
        $absencesLignes = $this->absencesDetaillees($eleves, $trimestre)->groupBy('eleve_id');

        $resultats = [];

        foreach ($eleves as $eleve) {
            $lignes = [];
            $totalCoef = 0;
            $totalPoints = 0.0;
            $lit = [0.0, 0];
            $sci = [0.0, 0];

            foreach ($affectations as $aff) {
                $mid = (int) $aff->matiere_id;

                if (!in_array($mid, $actives)) {
                    $lignes[$mid] = null;
                    continue;
                }

                $notesEleve = $parCle->get($mid . '-' . $eleve->id, collect());
                $devoirs = $notesEleve->where('type_evaluation', 'devoir')->sortBy('ordre')->pluck('valeur');
                $composition = $notesEleve->firstWhere('type_evaluation', 'composition')?->valeur ?? 0.0;

                $mc = $devoirs->isEmpty() ? 0.0 : round($devoirs->avg(), 2);
                $moyenne = round(($mc + $composition) / 2, 2);
                $noteDefinitive = round($moyenne * $aff->coefficient, 2);

                $lignes[$mid] = [
                    'devoirs'         => $devoirs->values()->all(),
                    'mc'              => $mc,
                    'composition'     => (float) $composition,
                    'moyenne'         => $moyenne,
                    'coefficient'     => (int) $aff->coefficient,
                    'note_definitive' => $noteDefinitive,
                    'rang'            => null,
                    'ex'              => false,
                    'appreciation'    => self::appreciation($moyenne),
                ];

                $totalCoef += $aff->coefficient;
                $totalPoints += $noteDefinitive;

                $groupe = $aff->matiere->groupe ?? 'autre';
                if ($groupe === 'litteraire') {
                    $lit[0] += $noteDefinitive;
                    $lit[1] += $aff->coefficient;
                } elseif ($groupe === 'scientifique') {
                    $sci[0] += $noteDefinitive;
                    $sci[1] += $aff->coefficient;
                }
            }

            $lignesEleve = $absencesLignes->get($eleve->id, collect());
            $absents = $lignesEleve->where('statut', 'absent');
            $heuresAbsence = (int) $absents->sum(fn($l) => $l->appel->duree);
            $heuresJustifiees = (int) $absents->filter(fn($l) => $l->justifie_effectif)->sum(fn($l) => $l->appel->duree);

            $resultats[$eleve->id] = [
                'eleve'           => $eleve,
                'lignes'          => $lignes,
                'total_coef'      => $totalCoef,
                'total_points'    => round($totalPoints, 2),
                'moyenne'         => $totalCoef > 0 ? round($totalPoints / $totalCoef, 2) : null,
                'moy_litterature' => $lit[1] > 0 ? round($lit[0] / $lit[1], 2) : null,
                'moy_science'     => $sci[1] > 0 ? round($sci[0] / $sci[1], 2) : null,
                'absences'        => [
                    'heures'            => $heuresAbsence,
                    'heures_justifiees' => $heuresJustifiees,
                    'retards'           => $lignesEleve->where('statut', 'retard')->count(),
                    'libelle'           => self::formaterHeures($heuresAbsence),
                ],
                'rang'            => null,
                'ex'              => false,
            ];
        }

        // Rang, moyenne forte / faible et moyenne de classe pour chaque matière
        $statsMatieres = [];
        foreach ($affectations as $aff) {
            $mid = (int) $aff->matiere_id;

            if (!in_array($mid, $actives) || empty($resultats)) {
                continue;
            }

            $moyennes = [];
            foreach ($resultats as $id => $r) {
                $moyennes[$id] = $r['lignes'][$mid]['moyenne'];
            }

            foreach ($this->classer($moyennes) as $id => $info) {
                $resultats[$id]['lignes'][$mid]['rang'] = $info['rang'];
                $resultats[$id]['lignes'][$mid]['ex'] = $info['ex'];
            }

            $statsMatieres[$mid] = [
                'forte'   => max($moyennes),
                'faible'  => min($moyennes),
                'moyenne' => round(array_sum($moyennes) / count($moyennes), 2),
            ];
        }

        // Rang général du trimestre
        $moyennesEleves = array_filter(array_map(fn($r) => $r['moyenne'], $resultats), fn($m) => $m !== null);
        foreach ($this->classer($moyennesEleves) as $id => $info) {
            $resultats[$id]['rang'] = $info['rang'];
            $resultats[$id]['ex'] = $info['ex'];
        }

        $stats = [
            'effectif'       => $eleves->count(),
            'effectif_g'     => $eleves->where('sexe', 'M')->count(),
            'effectif_f'     => $eleves->where('sexe', 'F')->count(),
            'moyenne_classe' => $moyennesEleves ? round(array_sum($moyennesEleves) / count($moyennesEleves), 2) : null,
            'plus_forte'     => $moyennesEleves ? max($moyennesEleves) : null,
            'plus_faible'    => $moyennesEleves ? min($moyennesEleves) : null,
            'moyenne_classe_annuelle' => null,
        ];

        return [
            'classe'           => $classe,
            'trimestre'        => $trimestre,
            'affectations'     => $affectations,
            'matieres_actives' => $actives,
            'eleves'           => $resultats,
            'stats_matieres'   => $statsMatieres,
            'stats'            => $stats,
        ];
    }

    /**
     * Classement avec ex æquo : [id => valeur] devient [id => ['rang' => n, 'ex' => bool]].
     * Deux élèves à égalité ont le même rang (1, 2, 2, 4...).
     */
    private function classer(array $valeurs): array
    {
        $classement = [];

        foreach ($valeurs as $id => $valeur) {
            $meilleurs = 0;
            $egaux = 0;

            foreach ($valeurs as $autreId => $autre) {
                if ($autreId === $id) {
                    continue;
                }
                if (abs($autre - $valeur) < 0.001) {
                    $egaux++;
                } elseif ($autre > $valeur) {
                    $meilleurs++;
                }
            }

            $classement[$id] = ['rang' => $meilleurs + 1, 'ex' => $egaux > 0];
        }

        return $classement;
    }
}
