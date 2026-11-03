<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            // Serveur qui héberge la copie ; null tant qu'elle n'est pas placée (attente de paiement).
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
        });

        $locale = DB::table('machines')->where('nom', 'locale')->value('id');

        if ($locale !== null) {
            DB::table('deploiements')->whereNull('machine_id')->update(['machine_id' => $locale]);
        }
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_id');
        });
    }
};
