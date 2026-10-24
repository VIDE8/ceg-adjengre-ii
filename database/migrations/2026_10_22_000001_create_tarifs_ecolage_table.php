<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Écolage de base : un montant par classe et par année scolaire
        Schema::create('tarifs_ecolage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedInteger('montant'); // en francs CFA
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'classe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs_ecolage');
    }
};
