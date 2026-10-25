<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copie du profil calculé (stats, réputation, Souk Score) : la recherche de devs
     * trie par Souk Score sans tout recalculer. Le profil d'un dev reste calculé en direct.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('souk_score')->default(0)->index();
            $table->json('profil_calcule')->nullable();
            $table->timestamp('profil_calcule_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['souk_score']);
            $table->dropColumn(['souk_score', 'profil_calcule', 'profil_calcule_le']);
        });
    }
};
