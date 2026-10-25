<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retirer une appli la sort du store sans toucher aux copies de ses clients ni à leurs
     * factures (suppression douce) : avant, la suppression effaçait aussi leurs déploiements.
     */
    public function up(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
