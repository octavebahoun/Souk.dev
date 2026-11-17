<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mesure d'audience sans cookie : une ligne par page vue. Ni adresse IP, ni compte : le
     * visiteur n'est qu'une empreinte qui change chaque jour (on compte, on ne suit personne).
     */
    public function up(): void
    {
        Schema::create('visites', function (Blueprint $table) {
            $table->id();
            $table->string('chemin', 255);
            $table->string('referent', 120)->nullable();
            $table->string('source', 60)->nullable();
            $table->char('pays', 2)->nullable();
            $table->string('appareil', 12);
            $table->char('visiteur', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['visiteur', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites');
    }
};
