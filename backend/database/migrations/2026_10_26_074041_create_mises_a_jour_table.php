<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mises à jour demandées par le client pour sa copie : la version de départ, la version visée,
 * la sauvegarde des volumes prise avant, et ce qui s'est passé (réussie, rétablie, échouée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mises_a_jour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deploiement_id')->constrained('deploiements')->cascadeOnDelete();
            $table->foreignId('de_version_id')->nullable()->constrained('versions_applis')->nullOnDelete();
            $table->foreignId('vers_version_id')->constrained('versions_applis')->cascadeOnDelete();
            $table->string('statut')->index();
            $table->text('erreur')->nullable();
            $table->text('variables')->nullable();
            $table->string('sauvegarde')->nullable();
            $table->timestamp('termine_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mises_a_jour');
    }
};
