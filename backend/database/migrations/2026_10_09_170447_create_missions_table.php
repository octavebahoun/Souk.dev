<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('app_id')->nullable()->index(); // vide = recrutement direct
            $table->text('message');
            $table->unsignedInteger('budget')->nullable();             // en FCFA
            $table->date('delai')->nullable();
            $table->enum('statut', ['en_cours', 'terminee'])->default('en_cours');
            $table->unsignedTinyInteger('note')->nullable();           // 1 à 5
            $table->text('note_commentaire')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
