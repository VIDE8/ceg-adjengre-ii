<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matiere extends Model
{
    public const GROUPES = [
        'litteraire'   => 'Littéraire',
        'scientifique' => 'Scientifique',
        'autre'        => 'Autre',
    ];

    protected $fillable = ['nom', 'code', 'groupe'];

    public function affectations()
    {
        return $this->hasMany(Affectation::class);
    }
}
