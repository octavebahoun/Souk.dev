<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            // Version corrigée déployée par l'auteur d'un correctif (POST /correctifs/{id}/deploiement).
            $table->foreignId('correctif_id')->nullable()->constrained('correctifs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('correctif_id');
        });
    }
};
