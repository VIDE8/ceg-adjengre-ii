<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'écolage change chaque année : chaque paiement est rattaché à son année scolaire
        Schema::table('paiements', function (Blueprint $table) {
            $table->foreignId('annee_scolaire_id')->nullable()->after('eleve_id')
                ->constrained('annees_scolaires')->nullOnDelete();
        });

        // Anciens paiements : année qui contient leur date, sinon année active
        $annees = DB::table('annees_scolaires')->get();

        foreach (DB::table('paiements')->whereNull('annee_scolaire_id')->get() as $paiement) {
            $annee = $annees->first(fn($a) => $a->date_debut <= $paiement->date_paiement && $a->date_fin >= $paiement->date_paiement)
                ?? $annees->firstWhere('active', 1);

            if ($annee) {
                DB::table('paiements')->where('id', $paiement->id)->update(['annee_scolaire_id' => $annee->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annee_scolaire_id');
        });
    }
};
