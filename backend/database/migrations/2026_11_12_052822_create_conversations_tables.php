<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Échange entre deux devs : une invitation à accepter, puis des messages privés. Et les canaux
     * que chacun suit, pour un fil à sa mesure.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demandeur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destinataire_id')->constrained('users')->cascadeOnDelete();
            // Bug sur lequel le demandeur cherche de l'aide (facultatif).
            $table->foreignId('discussion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('statut', 20)->default('en_attente');
            $table->string('invitation', 500);
            $table->timestamp('repondu_le')->nullable();
            $table->timestamp('dernier_message_le')->nullable();
            $table->timestamp('lu_demandeur_le')->nullable();
            $table->timestamp('lu_destinataire_le')->nullable();
            $table->timestamps();

            // Une seule conversation par paire, dans un sens ou dans l'autre (vérifié à l'invitation).
            $table->unique(['demandeur_id', 'destinataire_id']);
            $table->index(['destinataire_id', 'statut']);
        });

        Schema::create('messages_prives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->text('texte');
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('canaux_suivis', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('canal_id')->constrained('canaux')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'canal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canaux_suivis');
        Schema::dropIfExists('messages_prives');
        Schema::dropIfExists('conversations');
    }
};
