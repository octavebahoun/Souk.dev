<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canaux', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nom');
            $table->string('description', 300)->nullable();
            $table->string('pays', 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canaux');
    }
};
