<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'actif', 'doit_changer_mot_de_passe'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Rôles que le directeur peut attribuer depuis la page Utilisateurs
    public const ROLES_ATTRIBUABLES = [
        'professeur' => 'Professeur',
        'comptable'  => 'Comptable',
        'surveillant' => 'Surveillant',
    ];

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    // Matières enseignées (une ligne par classe et par matière)
    public function affectations()
    {
        return $this->hasMany(Affectation::class, 'professeur_id');
    }

    // Classes dont il est professeur titulaire
    public function classesTitulaire()
    {
        return $this->hasMany(Classe::class, 'professeur_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
        ];
    }
}
