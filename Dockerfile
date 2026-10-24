<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Matiere;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // EN PRODUCTION : seulement le compte du directeur et les matières, et seulement si la
        // base est vide. Aucun compte de test, aucun mot de passe « password ».
        if (app()->environment('production')) {
            if (User::count() > 0) {
                return;
            }

            $email = env('ADMIN_EMAIL');
            $motDePasse = env('ADMIN_PASSWORD');

            if (!$email || !$motDePasse) {
                throw new \RuntimeException('Définis les variables ADMIN_EMAIL et ADMIN_PASSWORD pour créer le compte du directeur.');
            }

            User::create([
                'name' => env('ADMIN_NAME', 'Directeur'),
                'email' => $email,
                'password' => Hash::make($motDePasse),
                'role' => 'admin',
                'actif' => true,
                'doit_changer_mot_de_passe' => true, // il choisit son propre mot de passe à la première connexion
            ]);

            $this->creerMatieres();

            return;
        }

        // EN DÉVELOPPEMENT (ton ordinateur) : comptes de test.
        // Le script peut être relancé sans risque : il ne crée que ce qui manque.
        User::firstOrCreate(
            ['email' => 'admin@ecole.tg'],
            ['name' => 'Admin Directeur', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        User::firstOrCreate(
            ['email' => 'comptable@ecole.tg'],
            ['name' => 'Comptable École', 'password' => Hash::make('password'), 'role' => 'comptable']
        );

        for ($i = 1; $i <= 9; $i++) {
            User::firstOrCreate(
                ['email' => "professeur{$i}@ecole.tg"],
                ['name' => "Professeur $i", 'password' => Hash::make('password'), 'role' => 'professeur']
            );
        }

        $this->creerMatieres();
    }

    // Matières du collège, avec leur groupe pour les moyennes « Littérature » et « Science »
    private function creerMatieres(): void
    {
        $matieres = [
            ['Français',  'FR',    'litteraire'],
            ['Anglais',   'ANG',   'litteraire'],
            ['Histo-Géo', 'HG',    'litteraire'],
            ['ECM',       'ECM',   'litteraire'],
            ['SVT',       'SVT',   'scientifique'],
            ['Maths',     'MATHS', 'scientifique'],
            ['PCT',       'PCT',   'scientifique'],
            ['EPS',       'EPS',   'autre'],
            ['Musique',   'MUS',   'autre'],
        ];

        foreach ($matieres as [$nom, $code, $groupe]) {
            Matiere::firstOrCreate(['nom' => $nom], ['code' => $code, 'groupe' => $groupe]);
        }
    }
}