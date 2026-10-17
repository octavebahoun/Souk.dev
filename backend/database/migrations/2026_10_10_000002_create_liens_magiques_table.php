<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liens_magiques', function (Blueprint $table) {
            $table->id();
            // Le compte n'est créé qu'à l'ouverture du lien : on garde l'adresse, pas le compte.
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expire_le');
            $table->timestamp('utilise_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liens_magiques');
    }
};
