<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correctifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('depot_url');
            $table->string('demo_url')->nullable();
            $table->string('description', 1000)->nullable();
            $table->boolean('accepte')->default(false);
            $table->timestamps();

            $table->index(['discussion_id', 'accepte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correctifs');
    }
};
