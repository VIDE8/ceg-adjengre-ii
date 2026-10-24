<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Retard extends Model
{
    protected $fillable = ['eleve_id', 'classe_id', 'date', 'heure', 'motif', 'enregistre_par'];

    protected $casts = [
        'date' => 'date',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function enregistrePar()
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    // Numéro imprimé sur le billet : 2026-000123
    public function getNumeroAttribute(): string
    {
        return $this->date->format('Y') . '-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
