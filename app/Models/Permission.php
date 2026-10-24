<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'eleve_id', 'classe_id', 'type', 'date_debut', 'date_fin',
        'heure_sortie', 'heure_retour_prevue', 'heure_retour',
        'accompagnateur', 'motif', 'accordee_par',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function accordeePar()
    {
        return $this->belongsTo(User::class, 'accordee_par');
    }

    // Numéro imprimé sur la permission : PERM-2026-000004
    public function getNumeroAttribute(): string
    {
        return 'PERM-' . $this->date_debut->format('Y') . '-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}