<?php

namespace App\Paiement;

use App\Events\FactureModifiee;
use App\Jobs\LancerDeploiement;
use App\Jobs\ReactiverCopie;
use App\Models\Abonnement;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\AlerteAdmin;
use App\Notifications\PaiementRecu;
use App\Notifications\TropPercu;
use App\Paiement\Passerelles\DemandeSession;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserellePaiement;
use App\Paiement\Passerelles\PasserelleRefus;
use App\Paiement\Passerelles\VerificationPaiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Encaissement d'une facture : session de checkout, puis vérification par l'API du prestataire.
 * La copie ne démarre (ou ne reprend) que lorsque la vérification renvoie SUCCESS ; jamais sur
 * la seule foi du navigateur ou d'un webhook. Tout est idempotent : vérifier deux fois ne paie
 * pas deux fois.
 */
final class Encaissement
{
    /** Une session encore ouverte est réutilisée (double clic, retour arrière) : SasPay la garde une heure. */
    private const MINUTES_REUTILISATION = 30;

    public function __construct(
        private PasserellePaiement $passerelle,
        private GrandLivre $grandLivre,
    ) {}

    /**
     * @throws PasserelleIndisponible|PasserelleRefus
     */
    public function ouvrirSession(Facture $facture, User $client): Paiement
    {
        if ($facture->statut !== 'a_payer') {
            throw new ConflictHttpException($facture->estPayee() ? 'Cette facture est déjà payée.' : 'Cette facture ne peut plus être payée.');
        }

        $recente = $facture->paiements()
            ->where('statut', 'en_attente')
            ->where('passerelle', $this->passerelle->nom())
            ->whereNull('transaction_id')
            ->whereNotNull('checkout_url')
            ->where('created_at', '>=', now()->subMinutes(self::MINUTES_REUTILISATION))
            ->first();

        if ($recente !== null) {
            return $recente;
        }

        // Une transaction attend encore la validation du client (USSD) : une seconde session
        // ferait payer deux fois. On la revérifie d'abord ; toujours en attente, on refuse.
        $enValidation = $facture->paiements()
            ->where('statut', 'en_attente')
            ->where('passerelle', $this->passerelle->nom())
            ->whereNotNull('transaction_id')
            ->where('created_at', '>=', now()->subHours(2))
            ->first();

        if ($enValidation !== null) {
            try {
                $enValidation = $this->verifier($enValidation);
            } catch (PasserelleIndisponible|PasserelleRefus) {
                // Sans réponse du prestataire, la prudence reste de ne pas ouvrir de seconde session.
            }

            if ($facture->refresh()->statut !== 'a_payer') {
                throw new ConflictHttpException('Cette facture vient d’être payée.');
            }

            if ($enValidation->statut === 'en_attente') {
                throw new ConflictHttpException('Un paiement est en cours de validation : confirmez-le sur votre téléphone, ou attendez sa réponse avant de réessayer.');
            }
        }

        $paiement = Paiement::query()->create([
            'facture_id' => $facture->id,
            'client_id' => $client->id,
            'passerelle' => $this->passerelle->nom(),
            'montant' => $facture->montant_total,
            'statut' => 'en_attente',
        ]);

        $facture->loadMissing('abonnement.appli');

        try {
            $session = $this->passerelle->creerSession(new DemandeSession(
                montant: $facture->montant_total,
                description: "Souk.dev — {$facture->abonnement->appli?->nom} — facture {$facture->numero}",
                email: $client->email ?: (string) config('mail.from.address'),
                nom: $client->name ?: 'Client Souk.dev',
                urlRetour: config('facturation.retour_paiement').'?facture='.$facture->id,
                metadata: ['facture_id' => $facture->id, 'paiement_id' => $paiement->id],
                pays: $client->pays,
            ));
        } catch (PasserelleIndisponible|PasserelleRefus $exception) {
            $paiement->forceFill(['statut' => 'annule', 'erreur' => mb_substr($exception->getMessage(), 0, 250)])->save();

            throw $exception;
        }

        $paiement->forceFill([
            'session_id' => $session->id,
            'session_slug' => $session->slug,
            'checkout_url' => $session->urlPaiement,
        ])->save();

        return $paiement;
    }

    /** Revérifie le dernier paiement ouvert d'une facture (page de retour, webhook, rapprochement). */
    public function verifierFacture(Facture $facture): Facture
    {
        $paiements = $facture->paiements()->whereNotNull('session_id')->whereNotIn('statut', ['reussi', 'annule'])->get();

        foreach ($paiements as $paiement) {
            try {
                $this->verifier($paiement);
            } catch (PasserelleIndisponible|PasserelleRefus $exception) {
                Log::warning('paiement: vérification impossible', ['paiement' => $paiement->id, 'erreur' => $exception->getMessage()]);
            }
        }

        return $facture->refresh();
    }

    /**
     * L'état fait foi chez le prestataire : statut de la session (dernière transaction), puis
     * vérification de la transaction. Une transaction refusée laisse la session ouverte : le
     * client peut réessayer sur la même page. Une tentative « annulée » qui porte une transaction
     * se revérifie : un succès tardif (USSD validé après l'expiration de la session) est crédité.
     *
     * @throws PasserelleIndisponible|PasserelleRefus
     */
    public function verifier(Paiement $paiement): Paiement
    {
        // Seule la passerelle qui a ouvert la session peut en dire l'état (jamais le simulateur
        // pour une session SasPay, ni l'inverse, par exemple après un changement de passerelle).
        if ($paiement->statut === 'reussi' || ($paiement->statut === 'annule' && $paiement->transaction_id === null)
            || $paiement->session_id === null || $paiement->passerelle !== $this->passerelle->nom()) {
            return $paiement;
        }

        $session = null;

        try {
            $session = $this->passerelle->statutSession($paiement->session_id);
        } catch (PasserelleRefus $exception) {
            // 404 ou 410 : la session n'existe plus chez le prestataire. Sans transaction, la
            // tentative est close ; avec une transaction, c'est elle qui décide.
            if (! in_array($exception->statutHttp, [404, 410], true)) {
                throw $exception;
            }

            if ($paiement->transaction_id === null) {
                $paiement->forceFill(['statut' => 'annule', 'verifie_le' => now()])->save();

                return $paiement;
            }
        }

        $transaction = $session?->transactionId ?? $paiement->transaction_id;
        $paiement->verifie_le = now();

        if ($transaction === null) {
            $paiement->statut = in_array($session?->statut, ['CANCELLED', 'EXPIRED'], true) ? 'annule' : 'en_attente';
            $paiement->save();

            return $paiement;
        }

        $verification = $this->passerelle->verifierPaiement($transaction);
        $paiement->forceFill([
            'transaction_id' => $transaction,
            'reference' => $verification->reference,
            'frais_client' => $verification->fraisClient,
            'montant_debite' => $verification->montantDebite,
        ]);

        if ($verification->reussi()) {
            return $this->appliquerVerificationReussie($paiement, $verification);
        }

        // L'état de la transaction décide, pas l'expiration de la session : une transaction en
        // attente reste en attente (elle peut encore aboutir).
        $paiement->statut = match ($verification->statut) {
            'FAILED' => 'echoue',
            'CANCELLED' => 'annule',
            default => 'en_attente',
        };
        $paiement->save();

        return $paiement;
    }

    /**
     * SUCCESS chez le prestataire. En francs CFA, le montant doit être celui de la facture (sinon
     * rien n'est appliqué et l'équipe vérifie) ; si SasPay a retenu des frais malgré ADD_ON, ils
     * passent en charge. Une carte peut être débitée dans une autre devise (conversion par
     * SasPay) : la transaction de notre session est créditée et l'équipe vérifie le montant reçu.
     */
    private function appliquerVerificationReussie(Paiement $paiement, VerificationPaiement $verification): Paiement
    {
        $devise = $verification->devise ?? 'XOF';
        $fraisRetenus = 0;

        if ($devise === 'XOF' && $verification->montantDemande !== $paiement->montant) {
            $dejaSignale = $paiement->erreur !== null;
            $paiement->forceFill(['statut' => 'en_attente', 'erreur' => 'Montant à vérifier par l’équipe.'])->save();
            Log::critical('paiement: montant incohérent', ['paiement' => $paiement->id, 'attendu' => $paiement->montant, 'recu' => $verification->montantDemande]);

            if (! $dejaSignale) {
                AlerteAdmin::envoyer("Paiement n° {$paiement->id} : montant à vérifier", [
                    "SasPay a confirmé une transaction de {$verification->montantDemande} F pour une facture de {$paiement->montant} F.",
                    'Rien n’a été appliqué. Vérifiez la transaction '.($verification->reference ?? $paiement->transaction_id).' dans le tableau de bord SasPay.',
                ]);
            }

            return $paiement;
        }

        if ($devise === 'XOF' && $verification->montantNet !== null && $verification->montantNet < $paiement->montant) {
            $fraisRetenus = $paiement->montant - $verification->montantNet;
        }

        $paiement->save();
        $this->appliquerSucces($paiement, $fraisRetenus);

        if ($devise !== 'XOF') {
            AlerteAdmin::envoyer("Paiement n° {$paiement->id} en {$devise}", [
                "La facture ({$paiement->montant} F) a été payée en {$devise} (carte, conversion par SasPay) : elle est créditée.",
                'Vérifiez le montant reçu sur le compte SasPay, référence '.($verification->reference ?? $paiement->transaction_id).'.',
            ]);
        }

        if ($fraisRetenus > 0) {
            AlerteAdmin::envoyer("Paiement n° {$paiement->id} : frais retenus par SasPay", [
                "SasPay a retenu {$fraisRetenus} F de frais alors que Souk.dev demande ADD_ON (frais payés par le client).",
                'Ils sont passés en charge au grand livre. Vérifiez le mode de frais du compte marchand (ALLOW_CLIENT_OVERRIDE).',
            ]);
        }

        return $paiement->refresh();
    }

    /** Une seule fois par paiement, sous verrou : facture payée, grand livre, copie lancée ou réactivée. */
    private function appliquerSucces(Paiement $paiement, int $fraisRetenus = 0): void
    {
        $resultat = DB::transaction(function () use ($paiement, $fraisRetenus): ?string {
            $facture = Facture::query()->lockForUpdate()->findOrFail($paiement->facture_id);
            $verrouille = Paiement::query()->lockForUpdate()->findOrFail($paiement->id);

            if ($verrouille->statut === 'reussi') {
                return null;
            }

            $verrouille->forceFill(['statut' => 'reussi', 'erreur' => null])->save();

            if ($facture->statut !== 'a_payer') {
                $this->grandLivre->tropPercu($facture, $verrouille);

                return 'trop_percu';
            }

            $facture->forceFill(['statut' => 'payee', 'payee_le' => now()])->save();
            $this->grandLivre->encaisser($facture, $verrouille, $fraisRetenus);
            $this->activer($facture);

            return 'payee';
        });

        if ($resultat === null) {
            return;
        }

        $facture = $paiement->facture()->first();

        if ($resultat === 'trop_percu') {
            Notification::route('mail', (string) config('app.admin_email'))->notify(new TropPercu($facture, $paiement->refresh()));

            return;
        }

        event(new FactureModifiee($facture));
        $facture->client?->notify(new PaiementRecu($facture));
    }

    /** Facture payée : l'abonnement repart, la copie démarre (première facture) ou reprend (suspendue). */
    private function activer(Facture $facture): void
    {
        $abonnement = Abonnement::query()->lockForUpdate()->findOrFail($facture->abonnement_id);
        $deploiement = $abonnement->deploiement()->first();

        if ($abonnement->statut === 'en_attente') {
            // Le premier mois court depuis le paiement, pas depuis la commande : la facture et
            // l'abonnement repartent du jour où la copie démarre.
            $debut = today();
            $facture->forceFill(['periode_debut' => $debut, 'periode_fin' => $debut->copy()->addMonthNoOverflow()->subDay()])->save();
            $abonnement->forceFill([
                'statut' => 'actif',
                'periode_debut' => $debut,
                'prochaine_echeance' => $debut->copy()->addMonthNoOverflow(),
            ])->save();

            if ($deploiement?->etat === 'attente_paiement') {
                $deploiement->changerEtat('en_file');
                LancerDeploiement::dispatch($deploiement->id)->afterCommit();
            }

            return;
        }

        if (! in_array($abonnement->statut, ['actif', 'en_retard', 'suspendu'], true)) {
            return;
        }

        if ($this->resteDesImpayesEchus($abonnement)) {
            return;
        }

        $suspendu = $abonnement->statut === 'suspendu';
        $abonnement->forceFill(['statut' => 'actif', 'suspendu_le' => null, 'avertissement_arret_le' => null])->save();

        if ($suspendu && $deploiement !== null) {
            ReactiverCopie::dispatch($deploiement->id)->afterCommit();
        }

        // Réactivée après la date anniversaire : rien n'a été servi pendant la suspension, le
        // paiement ouvre une période d'un mois à partir d'aujourd'hui (pas de facture déjà échue).
        if ($abonnement->prochaine_echeance !== null && $abonnement->prochaine_echeance->lte(today())) {
            $abonnement->forceFill(['prochaine_echeance' => today()->addMonthNoOverflow()])->save();
        }
    }

    private function resteDesImpayesEchus(Abonnement $abonnement): bool
    {
        return $abonnement->factures()
            ->where('statut', 'a_payer')
            ->where('echeance_le', '<', today())
            ->exists();
    }
}
