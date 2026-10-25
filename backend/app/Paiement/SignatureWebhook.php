<?php

namespace App\Paiement;

/**
 * Signature des webhooks SasPay : HMAC SHA-256 de « {timestamp}.{corps brut} », comparée en
 * temps constant, horodatage refusé au-delà de 300 secondes (un webhook intercepté ne se rejoue pas).
 */
final readonly class SignatureWebhook
{
    public function __construct(
        private string $secret,
        private int $tolerance = 300,
    ) {}

    public function signer(string $corps, int $horodatage): string
    {
        return hash_hmac('sha256', $horodatage.'.'.$corps, $this->secret);
    }

    public function verifier(string $corps, ?string $signature, ?string $horodatage, ?int $maintenant = null): bool
    {
        if ($this->secret === '' || $signature === null || $horodatage === null || ! ctype_digit($horodatage)) {
            return false;
        }

        if (abs(($maintenant ?? time()) - (int) $horodatage) > $this->tolerance) {
            return false;
        }

        return hash_equals($this->signer($corps, (int) $horodatage), strtolower(trim($signature)));
    }
}
