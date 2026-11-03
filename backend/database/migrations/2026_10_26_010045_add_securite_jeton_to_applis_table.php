<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jeton de la dernière analyse de sécurité demandée : un résultat dépassé est ignoré. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->uuid('securite_jeton')->nullable()->after('securite_problemes');
        });
    }

    public function down(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->dropColumn('securite_jeton');
        });
    }
};
