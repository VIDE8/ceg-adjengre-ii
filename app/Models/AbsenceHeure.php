<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceHeure extends Model
{
    protected $table = 'absences_heures';

    protected $fillable = ['appel_id', 'eleve_id', 'statut', 'justifie'];

    protected $casts = ['justifie' => 'boolean'];

    public function appel()
    {
        return $this->belongsTo(Appel::class);
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }
}
