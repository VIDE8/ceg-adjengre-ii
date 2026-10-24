<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un appel = une classe, un jour, une heure de cours, par un professeur
        Schema::create('appels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->nullOnDelete();
            $table->foreignId('professeur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('creneau');            // numéro de l'heure de cours (1 à 7)
            $table->unsignedTinyInteger('duree')->default(1);  // nombre d'heures (2 pour un double cours)
            $table->timestamps();

            $table->unique(['classe_id', 'date', 'creneau']);
        });

        // Seuls les absents et les retardataires sont enregistrés ; les présents n'ont pas de ligne
        Schema::create('absences_heures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_id')->constrained('appels')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->enum('statut', ['absent', 'retard']);
            $table->boolean('justifie')->default(false);
            $table->timestamps();

            $table->unique(['appel_id', 'eleve_id']);
        });

        // Un comportement noté pendant l'appel garde le lien avec cet appel
        Schema::table('comportements', function (Blueprint $table) {
            $table->foreignId('appel_id')->nullable()->after('classe_id')->constrained('appels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comportements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appel_id');
        });

        Schema::dropIfExists('absences_heures');
        Schema::dropIfExists('appels');
    }
};
