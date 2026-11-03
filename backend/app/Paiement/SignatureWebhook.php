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

    /**
     * Le secret de SASPAY_WEBHOOK_SECRET. En développement sans secret (dépôt tout juste cloné),
     * le simulateur et le webhook partagent un secret tiré de APP_KEY : les webhooks simulés
     * passent dès le premier démarrage. Jamais ailleurs : sans secret, tout webhook est refusé.
     */
    public static function depuisConfig(): self
    {
        $secret = (string) config('services.saspay.secret_webhook');

        if ($secret === '' && app()->environment('local')) {
            $secret = hash_hmac('sha256', 'souk-webhook-simulateur', (string) config('app.key'));
        }

        return new self($secret, (int) config('services.saspay.tolerance_webhook', 300));
    }

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
