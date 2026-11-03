<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backend intégré d'une copie : sa clé publique (dans l'adresse de connexion de ses utilisateurs)
 * et le secret de son API (chiffré), pour signer les jetons de ces utilisateurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->uuid('cle_publique')->nullable()->unique();
            $table->text('backend_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->dropUnique(['cle_publique']);
            $table->dropColumn(['cle_publique', 'backend_secret']);
        });
    }
};
