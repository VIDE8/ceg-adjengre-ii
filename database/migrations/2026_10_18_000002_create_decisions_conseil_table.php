<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions_conseil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();

            // Mentions positives
            $table->boolean('encouragement')->default(false);
            $table->boolean('felicitation')->default(false);
            $table->boolean('tableau_honneur')->default(false);

            // Avertissements et blâmes
            $table->boolean('avertissement_travail')->default(false);
            $table->boolean('avertissement_discipline')->default(false);
            $table->boolean('blame_travail')->default(false);
            $table->boolean('blame_discipline')->default(false);

            $table->string('observation')->nullable(); // ex. « Travail passable »
            $table->string('decision')->nullable();    // ex. « Passe en cinquième »

            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Une seule fiche par élève et par trimestre
            $table->unique(['eleve_id', 'trimestre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions_conseil');
    }
};