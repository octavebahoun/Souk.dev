<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            // Nom choisi par l'équipe (« locale » pour le démon du poste / de la plateforme).
            $table->string('nom', 64)->unique();
            // Démon Docker visé (DOCKER_HOST) : unix:///… ou tcp://hôte:port, null = démon local.
            $table->string('hote_docker', 255)->nullable()->unique();
            $table->string('etat', 16)->default('active');
            $table->boolean('joignable')->default(true);
            $table->string('version_docker', 32)->nullable();
            $table->integer('memoire_totale_mo')->nullable();
            // Mémoire réservée aux copies, déclarée par l'équipe ; null = sans limite déclarée.
            $table->integer('capacite_mo')->nullable();
            $table->string('sonde_erreur', 500)->nullable();
            $table->timestamp('sondee_le')->nullable();
            $table->timestamps();
        });

        // Le démon qui fait tourner la plateforme : les copies déjà lancées y sont rattachées.
        DB::table('machines')->insert([
            'nom' => 'locale',
            'hote_docker' => null,
            'etat' => 'active',
            'joignable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
