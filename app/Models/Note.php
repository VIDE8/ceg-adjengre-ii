<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'matiere_id',
        'trimestre_id',
        'type_evaluation', // devoir (note de classe) ou composition
        'ordre',           // numéro du devoir ; 1 pour la composition
        'valeur',          // note sur 20
        'saisi_par',       // professeur qui a saisi la note
    ];

    protected $casts = [
        'valeur' => 'float',
        'ordre' => 'integer',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function trimestre()
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function saisiPar()
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
