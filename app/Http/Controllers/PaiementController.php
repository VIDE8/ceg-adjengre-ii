<?php

namespace App\Http\Controllers;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Services\EcolageService;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function index(Request $request, EcolageService $ecolage)
    {
        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', (int) $request->annee_id)
            ?? $annees->firstWhere('active', true)
            ?? $annees->first();

        $query = Paiement::with('eleve.classe');

        if ($annee) {
            $query->where('annee_scolaire_id', $annee->id);
        }

        if ($request->has('search') && $request->search != '' && $request->search != '2026-EP-') {
            $search = $request->search;
            $query->whereHas('eleve', function ($q) use ($search) {
                $q->where('nom', 'LIKE', "%{$search}%")
                    ->orWhere('prenom', 'LIKE', "%{$search}%")
                    ->orWhere('matricule', 'LIKE', "%{$search}%");
            });
        }

        $paiements = $query->orderByDesc('date_paiement')->get();

        $eleves = Eleve::with('classe')->orderBy('nom')->orderBy('prenom')->get();
        $situations = $ecolage->situations($eleves, $annee);
        $bilan = collect($situations)->groupBy('statut')->map(fn($groupe) => $groupe->count());

        return view('paiements.index', compact('paiements', 'eleves', 'annees', 'annee', 'situations', 'bilan'));
    }

    public function store(Request $request, EcolageService $ecolage)
    {
        $request->validate([
            'eleve_id' => 'required|exists:eleves,id',
            'montant_verse' => 'required|numeric|min:1',
            'date_paiement' => 'required|date',
            'type_frais' => 'required|in:scolarite',
        ]);

        $annee = $ecolage->anneeActive();
        if (!$annee) {
            return back()->withInput()->withErrors(['eleve_id' => "Aucune année scolaire n'est active : le directeur doit en activer une."]);
        }

        $eleve = Eleve::with('classe')->findOrFail($request->eleve_id);
        $situation = $ecolage->situation($eleve, $annee);
        $montant = round((float) $request->montant_verse, 2);
        $classe = $eleve->classe ? $eleve->classe->niveau . ' ' . $eleve->classe->nom_classe : 'sa classe';
        $f = fn($n) => number_format($n, 0, ',', ' ') . ' F';

        // 1. Écolage non défini pour la classe
        if ($situation['du'] === null) {
            return back()->withInput()->withErrors(['montant_verse' => "L'écolage de la {$classe} n'est pas défini pour {$annee->libelle}. Définis-le d'abord dans la page « Écolage »."]);
        }

        // 2. Écolage déjà soldé : aucun surplus accepté
        if (in_array($situation['statut'], ['solde', 'surplus'])) {
            return back()->withInput()->withErrors(['montant_verse' => "{$eleve->nom} {$eleve->prenom} a déjà soldé son écolage ({$f($situation['du'])}). Aucun versement supplémentaire n'est accepté."]);
        }

        // 3. Le versement dépasse ce qu'il reste à payer
        if ($montant > $situation['reste'] + 0.004) {
            return back()->withInput()->withErrors(['montant_verse' => "Ce versement dépasse le reste à payer : il ne reste que {$f($situation['reste'])} (écolage {$f($situation['du'])}, déjà payé {$f($situation['paye'])})."]);
        }

        $solde = abs($montant - $situation['reste']) <= 0.004;
        $suite = $solde ? ' L\'écolage est maintenant soldé.' : ' Reste à payer : ' . $f($situation['reste'] - $montant) . '.';

        // Un seul reçu par élève et par année : les versements s'ajoutent
        $paiementExistant = Paiement::where('eleve_id', $eleve->id)
            ->where('type_frais', 'scolarite')
            ->where('annee_scolaire_id', $annee->id)
            ->first();

        if ($paiementExistant) {
            $paiementExistant->montant_verse += $montant;
            $paiementExistant->date_paiement = $request->date_paiement;
            $paiementExistant->save();

            return redirect()->back()->with('success', 'Le montant a été ajouté au paiement existant. Reçu conservé : ' . $paiementExistant->reference_recu . '.' . $suite);
        }

        // Nouveau reçu : REC-2026-001, REC-2026-002... (année de début de l'année scolaire)
        $prefixe = 'REC-' . $annee->date_debut->format('Y') . '-';
        $dernier = Paiement::where('reference_recu', 'LIKE', $prefixe . '%')->orderBy('id', 'desc')->first();
        $prochain = 1;

        if ($dernier) {
            $parties = explode('-', $dernier->reference_recu);
            $nombre = end($parties);
            if (is_numeric($nombre)) {
                $prochain = (int) $nombre + 1;
            }
        }

        $reference = $prefixe . str_pad($prochain, 3, '0', STR_PAD_LEFT);

        Paiement::create([
            'montant_verse' => $montant,
            'type_frais' => 'scolarite',
            'date_paiement' => $request->date_paiement,
            'eleve_id' => $eleve->id,
            'annee_scolaire_id' => $annee->id,
            'reference_recu' => $reference,
        ]);

        return redirect()->back()->with('success', 'Paiement enregistré avec succès ! Reçu : ' . $reference . '.' . $suite);
    }

    // Formulaire de modification d'un paiement
    public function edit($id, EcolageService $ecolage)
    {
        $paiement = Paiement::with('eleve.classe')->findOrFail($id);
        $annee = $paiement->annee_scolaire_id ? AnneeScolaire::find($paiement->annee_scolaire_id) : $ecolage->anneeActive();
        $situation = $ecolage->situation($paiement->eleve, $annee);

        // Ce que l'élève a versé en dehors de ce reçu (le plafond de ce reçu en dépend)
        $autres = round($situation['paye'] - (float) $paiement->montant_verse, 2);
        $plafond = $situation['du'] === null ? null : max(0, $situation['du'] - $autres);

        return view('paiements.edit', compact('paiement', 'situation', 'plafond', 'annee'));
    }

    public function update(Request $request, $id, EcolageService $ecolage)
    {
        $paiement = Paiement::with('eleve.classe')->findOrFail($id);

        $request->validate([
            'montant_verse' => 'required|numeric|gt:0',
            'date_paiement' => 'required|date',
            'type_frais' => 'required|string|in:scolarite',
        ]);

        $annee = $paiement->annee_scolaire_id ? AnneeScolaire::find($paiement->annee_scolaire_id) : $ecolage->anneeActive();
        $situation = $ecolage->situation($paiement->eleve, $annee);
        $montant = round((float) $request->montant_verse, 2);
        $autres = round($situation['paye'] - (float) $paiement->montant_verse, 2);

        // Pas de surplus : le total payé ne peut pas dépasser l'écolage de la classe
        if ($situation['du'] !== null && $autres + $montant > $situation['du'] + 0.004) {
            $maximum = number_format(max(0, $situation['du'] - $autres), 0, ',', ' ');

            return back()->withInput()->withErrors(['montant_verse' => "Ce montant dépasse l'écolage de la classe : le maximum pour ce reçu est {$maximum} F."]);
        }

        $paiement->update([
            'montant_verse' => $montant,
            'date_paiement' => $request->date_paiement,
            'type_frais' => $request->type_frais,
        ]);

        return redirect()->route('paiements.index')->with('success', 'Le paiement a été modifié avec succès !');
    }
}
