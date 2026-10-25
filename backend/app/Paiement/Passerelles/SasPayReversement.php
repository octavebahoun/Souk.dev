<?php

namespace App\Paiement\Passerelles;

/**
 * Reversement réel par payout SasPay. Branché seulement si REVERSEMENTS_REELS=true : un payout
 * fait sortir de l'argent du compte et exige une IP en liste blanche (403 ip_not_whitelisted sinon).
 * Frais en ADD_ON : le dev reçoit le montant entier, Souk.dev paie les frais.
 */
final class SasPayReversement implements PasserelleReversement
{
    /** Devise imposée par le pays du bénéficiaire (aucune conversion côté payout). */
    private const DEVISES = ['BJ' => 'XOF', 'CI' => 'XOF', 'SN' => 'XOF', 'TG' => 'XOF', 'BF' => 'XOF', 'ML' => 'XOF', 'NE' => 'XOF', 'CM' => 'XAF'];

    public function __construct(private ClientSasPay $client) {}

    public function nom(): string
    {
        return 'saspay';
    }

    public function initier(DemandeReversement $demande, string $cleIdempotence): string
    {
        $devise = self::DEVISES[$demande->pays] ?? throw new PasserelleRefus(422, 'invalid_country', 'Pays de reversement non pris en charge.');

        $payout = $this->client->post('/payouts/initialize/', [
            'amount' => Montants::versDecimal($demande->montant),
            'currency' => $devise,
            'country' => $demande->pays,
            'description' => $demande->description,
            'customer' => ['email' => $demande->email, 'first_name' => $demande->nom],
            'method' => $demande->reseau,
            'recipient' => ['msisdn' => $demande->numero],
            'metadata' => $demande->metadata,
            'fee_charge_mode' => 'ADD_ON',
        ], $cleIdempotence);

        if (! is_string($payout['id'] ?? null)) {
            throw new PasserelleIndisponible('Réponse de SasPay incomplète (payout sans identifiant).');
        }

        return $payout['id'];
    }

    public function verifier(string $payoutId): VerificationReversement
    {
        $payout = $this->client->get('/payouts/'.ClientSasPay::identifiant($payoutId).'/verify/');
        $demande = Montants::versEntier($payout['requested_amount'] ?? null);
        $debite = Montants::versEntier($payout['debited_amount'] ?? null);

        return new VerificationReversement(
            strtoupper((string) ($payout['status'] ?? 'PENDING')),
            $demande !== null && $debite !== null ? max(0, $debite - $demande) : Montants::versEntier($payout['client_fee'] ?? null),
        );
    }
}
