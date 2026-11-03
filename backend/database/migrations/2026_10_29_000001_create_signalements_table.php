<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('cible', 20);
            $table->unsignedBigInteger('cible_id');
            $table->string('motif', 20);
            $table->text('details')->nullable();
            $table->string('statut', 10)->default('ouvert');
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index(['cible', 'cible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
