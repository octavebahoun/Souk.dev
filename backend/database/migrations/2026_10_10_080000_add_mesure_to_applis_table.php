<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->string('mesure_statut')->default('en_cours');
            $table->unsignedInteger('memoire_max_mo')->nullable();
            $table->string('taille_recommandee')->nullable();
            $table->timestamp('mesure_le')->nullable();
            $table->unsignedInteger('mesure_jeton')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->dropColumn([
                'mesure_statut',
                'memoire_max_mo',
                'taille_recommandee',
                'mesure_le',
                'mesure_jeton',
            ]);
        });
    }
};
