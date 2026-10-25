<?php

namespace App\Paiement\Passerelles;

use App\Jobs\EnvoyerWebhookSimule;
use App\Models\SimulationSasPay;
use Illuminate\Support\Str;

/**
 * Simulateur de SasPay pour le développement et les tests : sessions de paiement (page locale
 * qui imite le checkout), statuts PENDING, SUCCESS et FAILED, vrais webhooks signés, et payouts.
 * Choisi automatiquement sans clé SasPay ; toujours utilisé pour les reversements, sauf
 * REVERSEMENTS_REELS=true. Jamais en production.
 */
final class SimulateurSasPay implements PasserellePaiement, PasserelleReversement
{
    /** Frais ajoutés au paiement (ADD_ON), comme MTN MoMo Bénin chez SasPay. */
    public const TAUX_PAIEMENT = 0.035;

    /** Frais d'un payout (ADD_ON, payés par Souk.dev) : 2,2 % + 50 F. */
    public const TAUX_REVERSEMENT = 0.022;

    public const FIXE_REVERSEMENT = 50;

    public function nom(): string
    {
        return 'simulee';
    }

    public function creerSession(DemandeSession $demande): SessionPaiement
    {
        $simulation = SimulationSasPay::query()->create([
            'type' => 'session',
            'montant' => $demande->montant,
            'frais' => (int) round($demande->montant * self::TAUX_PAIEMENT),
            'statut' => 'PENDING',
            'return_url' => $demande->urlRetour,
            'metadata' => [...$demande->metadata, 'description' => $demande->description],
        ]);

        return new SessionPaiement($simulation->id, 'sim-'.substr($simulation->id, 0, 8), url('/simulateur/saspay/'.$simulation->id));
    }

    public function statutSession(string $sessionId): StatutSession
    {
        $simulation = $this->trouver($sessionId, 'session');

        return new StatutSession(
            match ($simulation->statut) {
                'SUCCESS' => 'PAID',
                'CANCELLED' => 'CANCELLED',
                default => 'PENDING',
            },
            $simulation->transaction_id,
            $simulation->transaction_id !== null ? $simulation->statut : null,
        );
    }

    public function verifierPaiement(string $transactionId): VerificationPaiement
    {
        $simulation = SimulationSasPay::query()->where('type', 'session')->where('transaction_id', $transactionId)->first()
            ?? throw new PasserelleRefus(404, 'not_found', 'Transaction introuvable.');

        return new VerificationPaiement(
            $simulation->statut,
            $simulation->montant,
            $simulation->frais,
            $simulation->montant + $simulation->frais,
            $simulation->montant,
            $simulation->reference,
            'XOF',
        );
    }

    public function transactionsReussies(\DateTimeInterface $debut, \DateTimeInterface $fin): iterable
    {
        $simulations = SimulationSasPay::query()
            ->where('type', 'session')
            ->where('statut', 'SUCCESS')
            ->whereBetween('updated_at', [$debut, $fin])
            ->get();

        foreach ($simulations as $simulation) {
            yield new TransactionDistante(
                (string) $simulation->transaction_id,
                $simulation->reference,
                $simulation->montant,
                $simulation->montant,
                $simulation->updated_at->toIso8601String(),
                is_string($simulation->metadata['description'] ?? null) ? $simulation->metadata['description'] : null,
            );
        }
    }

    /**
     * Issue choisie sur la page du simulateur : succès, échec ou attente. Comme SasPay, un succès
     * ou un échec crée la transaction et envoie un webhook signé ; la vérification fait foi.
     */
    public function choisir(SimulationSasPay $simulation, string $issue, ?string $reseau): void
    {
        if ($simulation->statut === 'SUCCESS') {
            return;
        }

        $simulation->forceFill([
            'transaction_id' => $simulation->transaction_id ?? (string) Str::uuid(),
            'reference' => $simulation->reference ?? 'SIM-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'reseau' => $reseau,
            'statut' => match ($issue) {
                'succes' => 'SUCCESS',
                'echec' => 'FAILED',
                default => 'PENDING',
            },
        ])->save();

        if ($issue !== 'attente') {
            EnvoyerWebhookSimule::dispatch($simulation->id);
        }
    }

    public function initier(DemandeReversement $demande, string $cleIdempotence): string
    {
        $existante = SimulationSasPay::query()->where('type', 'payout')->where('reference', $cleIdempotence)->first();

        if ($existante !== null) {
            return $existante->id;
        }

        return SimulationSasPay::query()->create([
            'type' => 'payout',
            'reference' => $cleIdempotence,
            'montant' => $demande->montant,
            'frais' => (int) round($demande->montant * self::TAUX_REVERSEMENT) + self::FIXE_REVERSEMENT,
            'statut' => 'PENDING',
            'reseau' => $demande->reseau,
            // Un numéro qui finit par 0000 simule un refus du réseau (parcours d'échec).
            'metadata' => ['echouera' => str_ends_with(preg_replace('/\D/', '', $demande->numero) ?? '', '0000')],
        ])->id;
    }

    public function verifier(string $payoutId): VerificationReversement
    {
        $simulation = $this->trouver($payoutId, 'payout');

        if ($simulation->statut === 'PENDING') {
            $simulation->forceFill(['statut' => ($simulation->metadata['echouera'] ?? false) ? 'FAILED' : 'SUCCESS'])->save();
        }

        return new VerificationReversement(
            $simulation->statut,
            $simulation->statut === 'SUCCESS' ? $simulation->frais : null,
            $simulation->statut === 'FAILED' ? 'Le réseau a refusé le numéro (simulation).' : null,
        );
    }

    private function trouver(string $id, string $type): SimulationSasPay
    {
        return SimulationSasPay::query()->where('type', $type)->find($id)
            ?? throw new PasserelleRefus(404, 'not_found', 'Simulation introuvable.');
    }
}
