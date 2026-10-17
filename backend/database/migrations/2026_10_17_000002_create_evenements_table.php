<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titre');
            $table->text('description');
            $table->dateTime('date');
            $table->string('type');
            $table->string('lieu')->nullable();
            $table->string('lien')->nullable();
            $table->timestamps();
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['evenement_id', 'user_id']);
        });

        Schema::create('avis_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('note');
            $table->string('commentaire', 1000)->nullable();
            $table->timestamps();

            $table->unique(['evenement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis_evenements');
        Schema::dropIfExists('inscriptions');
        Schema::dropIfExists('evenements');
    }
};
