<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\DecisionConseil;
use App\Models\Eleve;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulletinController extends Controller
{
    private const ORDRE_NIVEAUX = ['6ème' => 0, '5ème' => 1, '4ème' => 2, '3ème' => 3];

    // Tous les bulletins d'une classe (un par page, prêts à imprimer)
    public function classe(Request $request, Classe $classe, BulletinService $service)
    {
        $this->autoriser($classe);

        return $this->afficher($request, $service, collect([$classe]), null, $classe, false);
    }

    // Le bulletin d'un seul élève
    public function eleve(Request $request, Classe $classe, Eleve $eleve, BulletinService $service)
    {
        $this->autoriser($classe);
        abort_if($eleve->classes_id != $classe->id, 404);

        return $this->afficher($request, $service, collect([$classe]), $eleve->id, $classe, false);
    }

    // Les bulletins de tous les élèves de toutes les classes, en un seul clic (directeur)
    public function ecole(Request $request, BulletinService $service)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_unless($user->hasRole('admin'), 403, 'Seul le directeur peut imprimer les bulletins de toute l\'école.');

        @set_time_limit(180);

        $classes = Classe::with('professeur')->get()
            ->sortBy(fn($c) => (self::ORDRE_NIVEAUX[$c->niveau] ?? 99) . '-' . $c->nom_classe)
            ->values();

        return $this->afficher($request, $service, $classes, null, null, true);
    }

    private function afficher(Request $request, BulletinService $service, $classes, ?int $eleveId, ?Classe $classeUnique, bool $ecoleEntiere)
    {
        $trimestres = $service->trimestresActifs();
        $trimestre = $trimestres->firstWhere('id', (int) $request->trimestre_id)
            ?? $service->trimestreParDefaut($trimestres);

        abort_if(!$trimestre, 404, "Aucun trimestre pour l'année scolaire active.");

        $groupes = [];

        foreach ($classes as $classe) {
            $resultats = $service->calculer($classe, $trimestre);

            // Seules les matières où des notes ont été saisies figurent sur le bulletin
            $matieresActives = $resultats['affectations']
                ->filter(fn($a) => in_array((int) $a->matiere_id, $resultats['matieres_actives']))
                ->values();

            // Pour toute l'école, on saute les classes où aucune note n'est saisie
            if ($ecoleEntiere && $matieresActives->isEmpty()) {
                continue;
            }

            // Nombre de colonnes « notes de classe » : le même pour toute la classe
            $maxDevoirs = 2;
            foreach ($resultats['eleves'] as $e) {
                foreach ($e['lignes'] as $ligne) {
                    if ($ligne) {
                        $maxDevoirs = max($maxDevoirs, count($ligne['devoirs']));
                    }
                }
            }

            $eleves = $eleveId
                ? array_intersect_key($resultats['eleves'], [$eleveId => true])
                : $resultats['eleves'];

            $decisions = DecisionConseil::where('trimestre_id', $trimestre->id)
                ->whereIn('eleve_id', array_keys($eleves))
                ->get()
                ->keyBy('eleve_id');

            $groupes[] = compact('classe', 'resultats', 'eleves', 'decisions', 'maxDevoirs', 'matieresActives');
        }

        $classe = $classeUnique;

        return view('bulletins.show', compact('classe', 'trimestre', 'trimestres', 'groupes', 'eleveId', 'ecoleEntiere'));
    }

    // Réservé à l'administrateur et au professeur titulaire de la classe
    private function autoriser(Classe $classe): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole('admin') && $classe->professeur_id != $user->id) {
            abort(403, 'Seul le professeur titulaire de la classe peut imprimer ses bulletins.');
        }
    }
}
