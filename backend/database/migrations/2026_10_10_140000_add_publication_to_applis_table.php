<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->text('description')->default('');
            $table->string('categorie')->default('autre');
            $table->unsignedInteger('prix')->default(0);
            $table->string('type_prix')->default('mensuel');
            $table->string('demo_url')->nullable();
            $table->json('stack')->nullable();
            $table->json('captures')->nullable();
            $table->string('backend')->default('propre');
            $table->json('variables_client')->nullable();
            $table->string('securite_statut')->default('en_cours');
            $table->timestamp('securite_analyse_le')->nullable();
            $table->json('securite_problemes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applis', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'categorie',
                'prix',
                'type_prix',
                'demo_url',
                'stack',
                'captures',
                'backend',
                'variables_client',
                'securite_statut',
                'securite_analyse_le',
                'securite_problemes',
            ]);
        });
    }
};
