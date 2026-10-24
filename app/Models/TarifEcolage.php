<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarifEcolage extends Model
{
    protected $table = 'tarifs_ecolage';

    protected $fillable = ['annee_scolaire_id', 'classe_id', 'montant'];

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }
}
