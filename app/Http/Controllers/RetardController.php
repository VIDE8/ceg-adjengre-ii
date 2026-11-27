<?php

namespace App\Http\Controllers;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Retard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RetardController extends Controller
{
    private const ORDRE_NIVEAUX = ['6ème' => 0, '5ème' => 1, '4ème' => 2, '3ème' => 3];

    // Retards d'une journée (aujourd'hui par défaut) + formulaire d'enregistrement
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $date = $request->filled('date') ? \Carbon\Carbon::parse($request->date)->toDateString() : today()->toDateString();

        $retards = Retard::with(['eleve', 'classe', 'enregistrePar'])
            ->where('date', $date)
            ->orderByDesc('heure')
            ->get();

        // Nombre de retards de chaque élève depuis le début de l'année scolaire active
        $debut = $this->debutAnnee();
        $totaux = Retard::selectRaw('eleve_id, COUNT(*) as total')
            ->whereIn('eleve_id', $retards->pluck('eleve_id'))
            ->where('date', '>=', $debut)
            ->groupBy('eleve_id')
            ->pluck('total', 'eleve_id');

        $eleves = Eleve::with('classe')->orderBy('nom')->orderBy('prenom')->get();

        return view('retards.index', compact('date', 'retards', 'totaux', 'eleves'));
    }

    // Règles de validation : les mêmes pour le formulaire et pour la synchronisation hors ligne
    public static function regles(): array
    {
        return [
            'eleve_id' => 'required|exists:eleves,id',
            'date'     => 'required|date',
            'heure'    => 'required|date_format:H:i',
            'motif'    => 'nullable|string|max:150',
        ];
    }

    public static function messages(): array
    {
        return [
            'eleve_id.required' => 'Choisis l\'élève dans la liste.',
            'heure.date_format' => 'L\'heure doit être au format hh:mm.',
        ];
    }

    // Enregistre un retard déjà validé (formulaire ou synchronisation hors ligne)
    public function creer(User $user, array $data): Retard
    {
        $eleve = Eleve::findOrFail($data['eleve_id']);

        return Retard::create($data + [
            'classe_id'      => $eleve->classes_id,
            'enregistre_par' => $user->id,
        ]);
    }

    // Enregistre le retard puis ouvre le billet d'entrée, prêt à imprimer
    public function store(Request $request)
    {
        $data = $request->validate(self::regles(), self::messages());

        $retard = $this->creer(Auth::user(), $data);

        return redirect()->route('retards.billet', ['retard' => $retard->id, 'imprimer' => 1]);
    }

    // Billet d'entrée (deux exemplaires : élève et souche du surveillant)
    public function billet(Retard $retard)
    {
        $retard->load(['eleve.classe', 'enregistrePar']);

        // « 3ème retard de l'année » pour cet élève
        $rang = Retard::where('eleve_id', $retard->eleve_id)
            ->where('date', '>=', $this->debutAnnee())
            ->where('id', '<=', $retard->id)
            ->count();

        return view('retards.billet', compact('retard', 'rang'));
    }

    // Annulation d'une erreur de saisie : par son auteur ou par le directeur
    public function destroy(Retard $retard)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        abort_unless($user->hasRole('admin') || $retard->enregistre_par == $user->id, 403, 'Tu ne peux supprimer que les retards que tu as enregistrés.');

        $date = $retard->date->toDateString();
        $retard->delete();

        return redirect()->route('retards.index', ['date' => $date])->with('success', 'Retard supprimé.');
    }

    private function debutAnnee(): string
    {
        $annee = AnneeScolaire::where('active', true)->first();

        return $annee ? $annee->date_debut->toDateString() : now()->startOfYear()->toDateString();
    }
}
