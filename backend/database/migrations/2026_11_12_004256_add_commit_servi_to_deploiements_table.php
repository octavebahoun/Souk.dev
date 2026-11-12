<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Commit réellement cloné par la copie : l'auteur voit quel code il essaie. */
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->string('commit_servi', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->dropColumn('commit_servi');
        });
    }
};
