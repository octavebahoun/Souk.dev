<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nombre et somme des notes, recalculés à chaque avis : la moyenne et le tri du Store sans jointure. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->unsignedInteger('avis_nombre')->default(0)->after('vues');
            $table->unsignedInteger('avis_somme')->default(0)->after('avis_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->dropColumn(['avis_nombre', 'avis_somme']);
        });
    }
};
