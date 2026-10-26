<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** La version de l'appli que fait tourner chaque copie (null pour une copie de test d'un dépôt). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->foreignId('version_id')->nullable()->after('appli_id')->constrained('versions_applis')->nullOnDelete();
        });

        DB::table('deploiements')->whereNotNull('appli_id')->orderBy('id')->select(['id', 'appli_id'])->each(function (object $copie): void {
            $version = DB::table('versions_applis')->where('appli_id', $copie->appli_id)->where('numero', 1)->value('id');
            DB::table('deploiements')->where('id', $copie->id)->update(['version_id' => $version]);
        });
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('version_id');
        });
    }
};
