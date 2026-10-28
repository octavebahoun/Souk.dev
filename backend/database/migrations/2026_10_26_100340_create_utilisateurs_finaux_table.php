<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Utilisateurs d'une appli déployée (pas des comptes Souk.dev) : connectés par un code reçu par
 * SMS ou e-mail. Numéros et adresses chiffrés ; l'empreinte sert à les retrouver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs_finaux', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('deploiement_id')->constrained('deploiements')->cascadeOnDelete();
            $table->string('canal');
            $table->string('empreinte', 64);
            $table->text('destinataire');
            $table->timestamp('derniere_connexion_le')->nullable();
            $table->timestamps();

            $table->unique(['deploiement_id', 'empreinte']);
        });

        Schema::create('codes_connexion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deploiement_id')->constrained('deploiements')->cascadeOnDelete();
            $table->string('empreinte', 64);
            $table->string('code', 64);
            $table->unsignedTinyInteger('essais')->default(0);
            $table->timestamp('expire_le');
            $table->timestamps();

            $table->index(['deploiement_id', 'empreinte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_connexion');
        Schema::dropIfExists('utilisateurs_finaux');
    }
};
