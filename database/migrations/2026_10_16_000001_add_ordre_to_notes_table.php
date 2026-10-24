<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // Numéro du devoir (1, 2, 3...) ; toujours 1 pour la composition
            $table->unsignedTinyInteger('ordre')->default(1)->after('type_evaluation');

            // Une seule note par élève, matière, trimestre, type et numéro
            $table->unique(
                ['eleve_id', 'matiere_id', 'trimestre_id', 'type_evaluation', 'ordre'],
                'notes_creneau_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // Correction ici : On passe le nom de l'index sous forme de tableau pour éviter les erreurs MySQL
            $table->dropUnique(['eleve_id', 'matiere_id', 'trimestre_id', 'type_evaluation', 'ordre']);
            $table->dropColumn('ordre');
        });
    }
};
