<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\DecisionConseil;
use App\Models\Eleve;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConseilClasseController extends Controller
{
    // Classe supérieure, pour proposer « Passe en cinquième », etc.
    private const SUIVANT = ['6ème' => 'cinquième', '5ème' => 'quatrième', '4ème' => 'troisième', '3ème' => 'seconde'];

    // Moyenne annuelle (moyenne des trois trimestres) à partir de laquelle l'élève passe en classe supérieure
    private const MOYENNE_PASSAGE = 10;

    public function show(Request $request, Classe $classe, BulletinService $service)
    {
        $this->autoriser($classe);

        $trimestres = $service->trimestresActifs();
        $trimestre = $trimestres->firstWhere('id', (int) $request->trimestre_id)
            ?? $service->trimestreParDefaut($trimestres);

        $resultats = $trimestre ? $service->calculer($classe, $trimestre) : null;

        $decisions = $trimestre && $resultats
            ? DecisionConseil::where('trimestre_id', $trimestre->id)
            ->whereIn('eleve_id', array_keys($resultats['eleves']))
            ->get()
            ->keyBy('eleve_id')
            : collect();

        $suivant = self::SUIVANT[$classe->niveau] ?? 'classe supérieure';
        $choixDecision = ['Passe en ' . $suivant, 'Redouble', 'Exclu(e)'];

        // La décision de passage se prend au 3ème trimestre, d'après la moyenne annuelle
        $estTroisieme = $trimestre && (int) $trimestre->numero === 3;
        $moyennePassage = self::MOYENNE_PASSAGE;

        return view('conseil.show', compact('classe', 'trimestres', 'trimestre', 'resultats', 'decisions', 'choixDecision', 'estTroisieme', 'moyennePassage'));
    }

    public function store(Request $request, Classe $classe, BulletinService $service)
    {
        $this->autoriser($classe);

        $request->validate([
            'trimestre_id'            => 'required|exists:trimestres,id',
            'decisions'               => 'nullable|array',
            'decisions.*.observation' => 'nullable|string|max:150',
            'decisions.*.decision'    => 'nullable|string|max:100',
        ]);

        $trimestre = $service->trimestresActifs()->firstWhere('id', (int) $request->trimestre_id);
        abort_if(!$trimestre, 422, "Ce trimestre n'appartient pas à l'année scolaire active.");

        $eleveIds = Eleve::where('classes_id', $classe->id)->pluck('id')->all();
        $champs = array_merge(array_keys(DecisionConseil::MENTIONS), array_keys(DecisionConseil::SANCTIONS));
        $userId = Auth::id();

        DB::transaction(function () use ($request, $trimestre, $eleveIds, $champs, $userId) {
            foreach ($request->input('decisions', []) as $eleveId => $saisie) {
                // On ignore tout élève qui n'est pas dans cette classe
                if (!in_array((int) $eleveId, $eleveIds)) {
                    continue;
                }

                $donnees = [];
                foreach ($champs as $champ) {
                    $donnees[$champ] = !empty($saisie[$champ]); // case cochée = true
                }
                $donnees['observation'] = $saisie['observation'] ?? null;
                $donnees['decision'] = $saisie['decision'] ?? null;
                $donnees['saisi_par'] = $userId;

                DecisionConseil::updateOrCreate(
                    ['eleve_id' => (int) $eleveId, 'trimestre_id' => $trimestre->id],
                    $donnees
                );
            }
        });

        return redirect()
            ->route('conseil.show', ['classe' => $classe->id, 'trimestre_id' => $trimestre->id])
            ->with('success', 'Conseil de classe enregistré avec succès !');
    }

    // Réservé à l'administrateur et au professeur titulaire de la classe
    private function autoriser(Classe $classe): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole('admin') && $classe->professeur_id != $user->id) {
            abort(403, 'Seul le professeur titulaire de la classe peut gérer son conseil de classe.');
        }
    }
}
