<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appel extends Model
{
    protected $fillable = ['classe_id', 'matiere_id', 'professeur_id', 'date', 'creneau', 'duree'];

    protected $casts = ['date' => 'date'];

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function professeur()
    {
        return $this->belongsTo(User::class, 'professeur_id');
    }

    public function absences()
    {
        return $this->hasMany(AbsenceHeure::class);
    }
}
