<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notes')) {
            return;
        }

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();

            // devoir = note de classe (interrogation, devoir), plusieurs possibles
            // composition = une seule par élève, matière et trimestre
            $table->enum('type_evaluation', ['devoir', 'composition'])->default('devoir');

            $table->decimal('valeur', 4, 2); // note sur 20
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['eleve_id', 'trimestre_id', 'matiere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
