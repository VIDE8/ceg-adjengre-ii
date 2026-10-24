<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();

            // sortie  = l'élève quitte l'établissement pendant les cours (avec retour éventuel)
            // absence = autorisation d'absence d'un ou plusieurs jours
            $table->enum('type', ['sortie', 'absence']);

            $table->date('date_debut');
            $table->date('date_fin');                       // = date_debut pour une sortie
            $table->time('heure_sortie')->nullable();
            $table->time('heure_retour_prevue')->nullable();
            $table->time('heure_retour')->nullable();       // retour réel enregistré par le surveillant
            $table->string('accompagnateur', 100)->nullable(); // parent ou tuteur qui vient chercher l'élève
            $table->string('motif', 150);

            $table->foreignId('accordee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
