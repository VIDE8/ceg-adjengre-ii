<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Un compte désactivé ne peut plus se connecter mais garde son historique (notes saisies...)
            $table->boolean('actif')->default(true)->after('role');

            // Mot de passe provisoire : l'utilisateur doit en choisir un nouveau à sa première connexion
            $table->boolean('doit_changer_mot_de_passe')->default(false)->after('actif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['actif', 'doit_changer_mot_de_passe']);
        });
    }
};
