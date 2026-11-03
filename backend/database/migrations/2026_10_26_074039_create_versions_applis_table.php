<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versions d'une appli : 1 à la publication, puis une par mise à jour poussée par l'auteur.
 * Chaque version vérifiée garde son commit : une copie tourne sur un code précis, pas sur
 * « ce que contient la branche aujourd'hui ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versions_applis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appli_id')->constrained('applis')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('ref', 200)->nullable();
            $table->string('commit', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('statut')->index();
            $table->text('erreur')->nullable();
            $table->json('manifeste')->nullable();
            $table->timestamps();

            $table->unique(['appli_id', 'numero']);
        });

        // Les applis déjà publiées ont leur version 1 (sans commit connu : la branche publiée fait foi).
        $maintenant = now();
        DB::table('applis')->orderBy('id')->select(['id', 'created_at'])->each(function (object $appli) use ($maintenant): void {
            DB::table('versions_applis')->insert([
                'appli_id' => $appli->id,
                'numero' => 1,
                'statut' => 'publiee',
                'created_at' => $appli->created_at ?? $maintenant,
                'updated_at' => $maintenant,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versions_applis');
    }
};
