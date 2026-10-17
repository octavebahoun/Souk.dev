<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->foreignId('appli_id')->nullable()->change();
            $table->string('depot_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table) {
            $table->dropColumn('depot_url');
            $table->foreignId('appli_id')->nullable(false)->change();
        });
    }
};
