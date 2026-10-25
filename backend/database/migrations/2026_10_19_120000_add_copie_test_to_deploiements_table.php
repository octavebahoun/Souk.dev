<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->foreignId('discussion_id')->nullable()->constrained('discussions')->nullOnDelete();
            // Copie de test : heure après laquelle elle doit être arrêtée (le travail d'arrêt viendra ensuite).
            $table->timestamp('expire_le')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discussion_id');
            $table->dropColumn('expire_le');
        });
    }
};
