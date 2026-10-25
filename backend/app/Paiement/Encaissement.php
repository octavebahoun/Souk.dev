<?php

namespace App\Paiement;

use App\Events\FactureModifiee;
use App\Jobs\LancerDeploiement;
use App\Jobs\ReactiverCopie;
use App\Models\Abonnement;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
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
        private Facturation $facturation,
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
            ->whereNull('transaction_id')
            ->whereNotNull('checkout_url')
            ->where('created_at', '>=', now()->subMinutes(self::MINUTES_REUTILISATION))
            ->first();

        if ($recente !== null) {
            return $recente;
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
     * client peut réessayer sur la même page.
     *
     * @throws PasserelleIndisponible|PasserelleRefus
     */
    public function verifier(Paiement $paiement): Paiement
    {
        if (in_array($paiement->statut, ['reussi', 'annule'], true) || $paiement->session_id === null) {
            return $paiement;
        }

        try {
            $session = $this->passerelle->statutSession($paiement->session_id);
        } catch (PasserelleRefus $exception) {
            // 404 ou 410 : la session n'existe plus chez le prestataire, la tentative est close.
            if (in_array($exception->statutHttp, [404, 410], true) && $paiement->transaction_id === null) {
                $paiement->forceFill(['statut' => 'annule', 'verifie_le' => now()])->save();

                return $paiement;
            }

            throw $exception;
        }

        $transaction = $session->transactionId ?? $paiement->transaction_id;
        $paiement->verifie_le = now();

        if ($transaction === null) {
            $paiement->statut = in_array($session->statut, ['CANCELLED', 'EXPIRED'], true) ? 'annule' : 'en_attente';
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
            if (! $this->coherent($paiement, $verification)) {
                $paiement->forceFill(['statut' => 'echoue', 'erreur' => 'Montant ou devise différents de la facture.'])->save();
                Log::critical('paiement: montant incohérent', ['paiement' => $paiement->id, 'attendu' => $paiement->montant, 'recu' => $verification->montantDemande]);

                return $paiement;
            }

            $paiement->save();
            $this->appliquerSucces($paiement);

            return $paiement->refresh();
        }

        $paiement->statut = match (true) {
            in_array($session->statut, ['CANCELLED', 'EXPIRED'], true) => 'annule',
            $verification->echoue() => 'echoue',
            default => 'en_attente',
        };
        $paiement->save();

        return $paiement;
    }

    private function coherent(Paiement $paiement, VerificationPaiement $verification): bool
    {
        return $verification->montantDemande === $paiement->montant
            && ($verification->devise === null || $verification->devise === 'XOF');
    }

    /** Une seule fois par paiement, sous verrou : facture payée, grand livre, copie lancée ou réactivée. */
    private function appliquerSucces(Paiement $paiement): void
    {
        $resultat = DB::transaction(function () use ($paiement): ?string {
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
            $this->grandLivre->encaisser($facture, $verrouille);
            $this->activer($facture);

            return 'payee';
        });

        if ($resultat === null) {
            return;
        }

        $facture = $paiement->facture()->first();

        if ($resultat === 'trop_percu') {
            Notification::route('mail', (string) config('mail.from.address'))->notify(new TropPercu($facture, $paiement->refresh()));

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
            $abonnement->forceFill([
                'statut' => 'actif',
                'periode_debut' => $facture->periode_debut,
                'prochaine_echeance' => $facture->periode_fin->copy()->addDay(),
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

        // Réactivée après la date anniversaire : la période en cours est facturée tout de suite.
        if ($abonnement->prochaine_echeance !== null && $abonnement->prochaine_echeance->lte(today())) {
            $this->facturation->emettre($abonnement, $abonnement->prochaine_echeance);
            $abonnement->forceFill(['prochaine_echeance' => $abonnement->prochaine_echeance->copy()->addMonthNoOverflow()])->save();
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
