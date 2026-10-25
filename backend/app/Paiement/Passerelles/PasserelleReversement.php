<?php

namespace App\Paiement\Passerelles;

/**
 * Reversement (payout) vers le Mobile Money d'un dev. La clé d'idempotence, gardée en base,
 * est renvoyée à chaque nouvel essai de la même intention : un timeout ne crée jamais de doublon.
 */
interface PasserelleReversement
{
    public function nom(): string;

    /** @throws PasserelleIndisponible|PasserelleRefus */
    public function initier(DemandeReversement $demande, string $cleIdempotence): string;

    public function verifier(string $payoutId): VerificationReversement;
}
