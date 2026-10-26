<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes d'analyse Souk AI : la dernière d'une discussion s'affiche, toutes comptent pour la
 * limite de leur auteur (3 par 24 heures, échecs exclus) et gardent la consommation du fournisseur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')->constrained('discussions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('statut')->index();
            $table->text('texte')->nullable();
            $table->string('erreur')->nullable();
            $table->string('modele')->nullable();
            $table->unsignedInteger('jetons_entree')->default(0);
            $table->unsignedInteger('jetons_sortie')->default(0);
            $table->timestamp('consentement_le');
            $table->timestamp('termine_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['discussion_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses_ia');
    }
};
