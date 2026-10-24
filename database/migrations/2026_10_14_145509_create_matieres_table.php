<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('matieres')) {
            return;
        }

        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('code', 10)->nullable();
            // litteraire / scientifique / autre : sert aux moyennes
            // « Littérature » et « Science » du bulletin
            $table->string('groupe', 20)->default('autre');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matieres');
    }
};
