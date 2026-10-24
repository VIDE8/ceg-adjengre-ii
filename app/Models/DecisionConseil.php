<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionConseil extends Model
{
    protected $table = 'decisions_conseil';

    // Mentions positives (champ => libellé du bulletin)
    public const MENTIONS = [
        'encouragement'   => 'Encouragement',
        'felicitation'    => 'Félicitation',
        'tableau_honneur' => "Tableau d'honneur",
    ];

    // Avertissements et blâmes
    public const SANCTIONS = [
        'avertissement_travail'    => 'Avertissement travail',
        'avertissement_discipline' => 'Avertissement discipline',
        'blame_travail'            => 'Blâme travail',
        'blame_discipline'         => 'Blâme discipline',
    ];

    protected $fillable = [
        'eleve_id',
        'trimestre_id',
        'encouragement',
        'felicitation',
        'tableau_honneur',
        'avertissement_travail',
        'avertissement_discipline',
        'blame_travail',
        'blame_discipline',
        'observation',
        'decision',
        'saisi_par',
    ];

    protected $casts = [
        'encouragement' => 'boolean',
        'felicitation' => 'boolean',
        'tableau_honneur' => 'boolean',
        'avertissement_travail' => 'boolean',
        'avertissement_discipline' => 'boolean',
        'blame_travail' => 'boolean',
        'blame_discipline' => 'boolean',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function trimestre()
    {
        return $this->belongsTo(Trimestre::class);
    }
}
