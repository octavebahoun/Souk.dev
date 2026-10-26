<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une analyse survit à la suppression de sa discussion : elle compte toujours dans la limite de
 * son auteur (supprimer puis recréer un bug ne rend pas de nouvelles analyses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses_ia', function (Blueprint $table) {
            $table->dropForeign(['discussion_id']);
        });

        Schema::table('analyses_ia', function (Blueprint $table) {
            $table->foreignId('discussion_id')->nullable()->change();
            $table->foreign('discussion_id')->references('id')->on('discussions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analyses_ia', function (Blueprint $table) {
            $table->dropForeign(['discussion_id']);
        });

        DB::table('analyses_ia')->whereNull('discussion_id')->delete();

        Schema::table('analyses_ia', function (Blueprint $table) {
            $table->foreignId('discussion_id')->nullable(false)->change();
            $table->foreign('discussion_id')->references('id')->on('discussions')->cascadeOnDelete();
        });
    }
};
