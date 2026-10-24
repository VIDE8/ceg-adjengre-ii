<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            // Sexe (M ou F) : sert au bulletin et à l'effectif Garçons / Filles de la classe
            $table->enum('sexe', ['M', 'F'])->nullable()->after('prenom');

            // Statut de l'élève affiché sur le bulletin (ex. « R » pour redoublant)
            $table->string('statut', 10)->nullable()->after('matricule');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['sexe', 'statut']);
        });
    }
};
