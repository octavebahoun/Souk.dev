<?php

namespace App\Sms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SMS simulés (aucun fournisseur configuré, tests) : le message est gardé une heure, lisible en
 * développement sur /simulateur/sms ; les journaux n'ont que le numéro masqué.
 */
final class SmsSimule implements FournisseurSms
{
    public const CLE = 'simulateur:sms';

    public function envoyer(string $numero, string $message): void
    {
        $messages = Cache::get(self::CLE, []);
        array_unshift($messages, ['numero' => $numero, 'message' => $message, 'envoye_le' => now()->toIso8601String()]);
        Cache::put(self::CLE, array_slice($messages, 0, 50), now()->addHour());

        Log::info('sms: simulé', ['numero' => self::masquer($numero)]);
    }

    /** « +229 •• •• •• 12 » : jamais un numéro complet dans les journaux. */
    public static function masquer(string $numero): string
    {
        return substr($numero, 0, 4).' •• •• •• '.substr($numero, -2);
    }
}
