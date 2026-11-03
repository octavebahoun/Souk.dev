<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturation et encaissement (SasPay) : abonnements des copies clients, factures à date
 * anniversaire, tentatives de paiement, grand livre en partie double (entiers FCFA, jamais
 * modifié), reversements aux devs, avoirs, rapprochements et idempotence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deploiement_id')->unique()->constrained('deploiements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('dev_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('appli_id')->nullable()->constrained('applis')->nullOnDelete();
            $table->string('formule');
            $table->unsignedInteger('montant_hebergement');
            $table->unsignedInteger('prix_appli');
            $table->string('type_prix');
            $table->string('statut')->index();
            $table->date('periode_debut')->nullable();
            $table->date('prochaine_echeance')->nullable()->index();
            $table->timestamp('suspendu_le')->nullable();
            $table->timestamp('avertissement_arret_le')->nullable();
            $table->timestamp('resilie_le')->nullable();
            $table->timestamps();
        });

        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->unique();
            $table->foreignId('abonnement_id')->constrained('abonnements')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->date('echeance_le')->index();
            $table->unsignedInteger('montant_hebergement');
            $table->unsignedInteger('montant_appli');
            $table->unsignedInteger('montant_total');
            $table->string('statut')->index();
            $table->timestamp('payee_le')->nullable();
            $table->timestamp('rappel_envoye_le')->nullable();
            $table->timestamp('retard_signale_le')->nullable();
            $table->timestamps();
            $table->unique(['abonnement_id', 'periode_debut']);
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('passerelle');
            $table->string('session_id')->nullable()->unique();
            $table->string('session_slug')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('reference')->nullable();
            $table->unsignedInteger('montant');
            $table->unsignedInteger('frais_client')->nullable();
            $table->unsignedInteger('montant_debite')->nullable();
            $table->string('reseau')->nullable();
            $table->string('statut')->index();
            $table->string('erreur')->nullable();
            $table->timestamp('verifie_le')->nullable();
            $table->timestamps();
        });

        Schema::create('reversements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dev_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('montant');
            $table->unsignedInteger('frais')->nullable();
            $table->string('passerelle');
            $table->uuid('cle_idempotence')->unique();
            $table->string('payout_id')->nullable()->unique();
            $table->string('pays', 2);
            $table->string('reseau');
            $table->string('numero_masque');
            // Bénéficiaire figé à la demande (chiffré) : une reprise renvoie exactement le même corps.
            $table->text('beneficiaire');
            $table->string('declenchement');
            $table->string('statut')->index();
            $table->string('erreur')->nullable();
            $table->timestamp('verifie_le')->nullable();
            $table->timestamps();
        });

        Schema::create('avoirs', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->unique();
            $table->foreignId('facture_id')->constrained('factures')->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('montant_hebergement');
            $table->unsignedInteger('montant_appli');
            $table->string('motif', 500);
            $table->timestamps();
        });

        // Grand livre en partie double : chaque opération s'équilibre (somme nulle).
        // Signe : positif au débit, négatif au crédit. Une erreur se corrige par une écriture inverse.
        Schema::create('ecritures', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation')->index();
            $table->string('type');
            $table->string('compte');
            $table->foreignId('dev_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->bigInteger('montant');
            $table->foreignId('facture_id')->nullable()->constrained('factures')->restrictOnDelete();
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->restrictOnDelete();
            $table->foreignId('reversement_id')->nullable()->constrained('reversements')->restrictOnDelete();
            $table->foreignId('avoir_id')->nullable()->constrained('avoirs')->restrictOnDelete();
            $table->foreignId('contrepasse_id')->nullable()->constrained('ecritures')->restrictOnDelete();
            $table->string('libelle');
            $table->timestamp('cree_le')->useCurrent()->index();
            $table->index(['compte', 'dev_id']);
        });

        Schema::create('coordonnees_reversement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('pays', 2);
            $table->string('reseau');
            $table->text('numero');
            $table->string('numero_masque');
            $table->string('jeton_confirmation_hash')->nullable();
            $table->timestamp('jeton_expire_le')->nullable();
            $table->timestamp('confirme_le')->nullable();
            $table->timestamps();
        });

        Schema::create('simulations_saspay', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuid('transaction_id')->nullable()->unique();
            $table->string('reference')->nullable();
            $table->unsignedInteger('montant');
            $table->unsignedInteger('frais')->default(0);
            $table->string('statut');
            $table->string('reseau')->nullable();
            $table->text('return_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('webhooks_saspay', function (Blueprint $table) {
            $table->id();
            $table->string('empreinte', 64)->unique();
            $table->string('evenement');
            $table->string('transaction_id')->nullable()->index();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });

        Schema::create('rapprochements', function (Blueprint $table) {
            $table->id();
            $table->date('jour')->unique();
            $table->unsignedInteger('transactions_saspay');
            $table->unsignedInteger('paiements_locaux');
            $table->json('ecarts');
            $table->timestamps();
        });

        Schema::create('cles_idempotence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('route');
            $table->string('cle', 100);
            $table->string('empreinte_corps', 64);
            $table->unsignedSmallInteger('statut_http')->nullable();
            $table->longText('reponse')->nullable();
            $table->timestamp('expire_le')->index();
            $table->timestamps();
            $table->unique(['user_id', 'route', 'cle']);
        });

        // PostgreSQL : le grand livre refuse toute modification, et toute suppression hors
        // purge du jeu de démo (SET LOCAL soukdev.purge = 'demo', voir DemoSeeder).
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION soukdev_ecritures_immuables() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' AND current_setting('soukdev.purge', true) = 'demo' THEN
        RETURN OLD;
    END IF;
    RAISE EXCEPTION 'Le grand livre ne se modifie pas : passez une écriture inverse.';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER ecritures_immuables BEFORE UPDATE OR DELETE ON ecritures
    FOR EACH ROW EXECUTE FUNCTION soukdev_ecritures_immuables();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ecritures_immuables ON ecritures; DROP FUNCTION IF EXISTS soukdev_ecritures_immuables();');
        }

        Schema::dropIfExists('cles_idempotence');
        Schema::dropIfExists('rapprochements');
        Schema::dropIfExists('webhooks_saspay');
        Schema::dropIfExists('simulations_saspay');
        Schema::dropIfExists('coordonnees_reversement');
        Schema::dropIfExists('ecritures');
        Schema::dropIfExists('avoirs');
        Schema::dropIfExists('reversements');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('factures');
        Schema::dropIfExists('abonnements');
    }
};
