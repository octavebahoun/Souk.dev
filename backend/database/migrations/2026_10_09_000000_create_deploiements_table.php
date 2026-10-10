<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('depot_url');
            $table->string('taille');
            $table->timestamps();
        });

        Schema::create('deploiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appli_id')->constrained('applis')->cascadeOnDelete();
            $table->string('type');
            $table->string('taille');
            $table->string('etat');
            $table->string('url')->nullable();
            $table->text('erreur')->nullable();
            $table->string('copie')->nullable();
            $table->text('repertoire')->nullable();
            $table->json('variables');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploiements');
        Schema::dropIfExists('applis');
    }
};
