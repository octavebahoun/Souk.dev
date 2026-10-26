<?php

namespace App\Paiement\Passerelles;

/**
 * En production, tant que REVERSEMENTS_REELS n'est pas activé : aucun reversement ne part, et
 * aucun n'est déclaré réussi (le simulateur le ferait sans qu'un franc ne quitte le compte).
 */
final class ReversementsInactifs implements PasserelleReversement
{
    public function nom(): string
    {
        return 'inactif';
    }

    public function initier(DemandeReversement $demande, string $cleIdempotence): string
    {
        throw new PasserelleIndisponible('Les reversements sur Mobile Money ne sont pas encore ouverts.');
    }

    public function verifier(string $payoutId): VerificationReversement
    {
        throw new PasserelleIndisponible('Les reversements sur Mobile Money ne sont pas encore ouverts.');
    }
}
