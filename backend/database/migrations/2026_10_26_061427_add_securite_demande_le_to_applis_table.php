<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Moment de la dernière analyse demandée : une analyse restée « en cours » trop longtemps se relance. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->timestamp('securite_demande_le')->nullable()->after('securite_jeton');
        });
    }

    public function down(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->dropColumn('securite_demande_le');
        });
    }
};
