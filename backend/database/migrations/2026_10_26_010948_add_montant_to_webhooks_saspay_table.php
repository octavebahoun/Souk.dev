<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Montant annoncé par le webhook : seules les sessions du même montant sont revérifiées. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks_saspay', function (Blueprint $table) {
            $table->unsignedInteger('montant')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('webhooks_saspay', function (Blueprint $table) {
            $table->dropColumn('montant');
        });
    }
};
