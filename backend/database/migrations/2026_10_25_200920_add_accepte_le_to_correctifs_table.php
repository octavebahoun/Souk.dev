<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date d'acceptation : l'activité récente du Souk Score compte les correctifs acceptés sur 30 jours.
     */
    public function up(): void
    {
        Schema::table('correctifs', function (Blueprint $table) {
            $table->timestamp('accepte_le')->nullable()->after('accepte');
        });

        DB::table('correctifs')->where('accepte', true)->update(['accepte_le' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('correctifs', function (Blueprint $table) {
            $table->dropColumn('accepte_le');
        });
    }
};
