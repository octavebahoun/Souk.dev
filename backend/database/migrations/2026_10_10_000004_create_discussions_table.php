<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('canal_id')->nullable()->constrained('canaux')->restrictOnDelete();
            $table->unsignedBigInteger('app_id')->nullable()->index();
            $table->string('titre');
            $table->text('texte');
            $table->string('depot_url')->nullable();
            $table->string('demo_url')->nullable();
            $table->json('etiquettes');
            $table->json('bug')->nullable();
            $table->boolean('resolue')->default(false);
            $table->timestamps();

            $table->index(['resolue', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
