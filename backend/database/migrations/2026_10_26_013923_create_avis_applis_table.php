<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Avis des clients sur une appli : de 1 à 5, un par client et par appli. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avis_applis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appli_id')->constrained('applis')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->unique(['appli_id', 'user_id']);
            $table->index(['appli_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis_applis');
    }
};
