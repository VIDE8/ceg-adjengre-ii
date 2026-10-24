<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si des classes en double existent déjà (même niveau, même nom), on renomme les
        // doublons au lieu de bloquer : « Groupe A », « Groupe A (2) », « Groupe A (3) »...
        // Rien n'est supprimé : les élèves restent dans leur classe.
        $doublons = DB::table('classes')
            ->select('niveau', 'nom_classe')
            ->groupBy('niveau', 'nom_classe')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($doublons as $doublon) {
            $ids = DB::table('classes')
                ->where('niveau', $doublon->niveau)
                ->where('nom_classe', $doublon->nom_classe)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1)->values() as $i => $id) {
                DB::table('classes')->where('id', $id)->update([
                    'nom_classe' => $doublon->nom_classe . ' (' . ($i + 2) . ')',
                ]);
            }
        }

        // Sécurité côté base : impossible d'avoir deux « 6ème A »
        Schema::table('classes', function (Blueprint $table) {
            $table->unique(['niveau', 'nom_classe']);
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['niveau', 'nom_classe']);
        });
    }
};
