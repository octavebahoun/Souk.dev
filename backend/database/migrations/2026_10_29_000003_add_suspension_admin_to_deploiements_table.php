<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploiements', function (Blueprint $table): void {
            $table->string('suspension_admin_motif', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deploiements', function (Blueprint $table): void {
            $table->dropColumn('suspension_admin_motif');
        });
    }
};
