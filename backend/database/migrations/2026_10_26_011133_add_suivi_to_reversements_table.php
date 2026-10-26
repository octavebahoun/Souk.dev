<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des reversements : nombre d'appels à SasPay (une reprise refusée ne rend pas le solde
 * à l'aveugle) et alerte envoyée à l'équipe pour un reversement bloqué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reversements', function (Blueprint $table) {
            $table->unsignedSmallInteger('essais')->default(0)->after('statut');
            $table->timestamp('alerte_le')->nullable()->after('verifie_le');
        });
    }

    public function down(): void
    {
        Schema::table('reversements', function (Blueprint $table) {
            $table->dropColumn(['essais', 'alerte_le']);
        });
    }
};
