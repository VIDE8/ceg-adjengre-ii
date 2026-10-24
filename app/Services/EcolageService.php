<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\TarifEcolage;
use Illuminate\Support\Collection;

/**
 * Écolage dû (défini par classe et par année), déjà payé, reste à payer, et statut :
 * non_defini, non_paye, partiel, solde ou surplus.
 */
class EcolageService
{
    public function anneeActive(): ?AnneeScolaire
    {
        return AnneeScolaire::where('active', true)->first();
    }

    public function situation(Eleve $eleve, ?AnneeScolaire $annee = null): array
    {
        return $this->situations(collect([$eleve]), $annee)[$eleve->id];
    }

    /** Situation de plusieurs élèves d'un coup : [eleve_id => [...]]. */
    public function situations(Collection $eleves, ?AnneeScolaire $annee = null): array
    {
        $annee = $annee ?? $this->anneeActive();

        $tarifs = $annee
            ? TarifEcolage::where('annee_scolaire_id', $annee->id)->pluck('montant', 'classe_id')
            : collect();

        $payes = $annee
            ? Paiement::where('annee_scolaire_id', $annee->id)
            ->where('type_frais', 'scolarite')
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->selectRaw('eleve_id, SUM(montant_verse) as total')
            ->groupBy('eleve_id')
            ->pluck('total', 'eleve_id')
            : collect();

        $resultat = [];
        foreach ($eleves as $eleve) {
            $du = $tarifs->get($eleve->classes_id);
            $paye = round((float) ($payes->get($eleve->id) ?? 0), 2);
            $resultat[$eleve->id] = $this->calculer($du === null ? null : (float) $du, $paye);
        }

        return $resultat;
    }

    private function calculer(?float $du, float $paye): array
    {
        if ($du === null) {
            return ['du' => null, 'paye' => $paye, 'reste' => null, 'surplus' => 0.0, 'statut' => 'non_defini'];
        }

        $ecart = round($paye - $du, 2);

        $statut = match (true) {
            $ecart > 0.004      => 'surplus',
            abs($ecart) <= 0.004 => 'solde',
            $paye > 0           => 'partiel',
            default             => 'non_paye',
        };

        return [
            'du'      => $du,
            'paye'    => $paye,
            'reste'   => max(0.0, round($du - $paye, 2)),
            'surplus' => max(0.0, $ecart),
            'statut'  => $statut,
        ];
    }
}
