<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete(); // classe au moment du retard
            $table->date('date');
            $table->time('heure');                       // heure d'arrivée
            $table->string('motif', 150)->nullable();
            $table->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'eleve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retards');
    }
};
