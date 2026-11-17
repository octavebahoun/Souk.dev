<?php

declare(strict_types=1);

namespace App\Audience;

/**
 * Pays estimé sans adresse IP : d'après le fuseau horaire du navigateur (Africa/Porto-Novo →
 * Bénin), sinon d'après la région de sa langue (fr-SN → Sénégal). Une estimation : plusieurs
 * pays partagent parfois un fuseau (Africa/Lagos couvre le Nigeria, et souvent le Bénin).
 */
final class PaysEstime
{
    /** @var array<string, string> */
    private const FUSEAUX = [
        'Africa/Porto-Novo' => 'BJ', 'Africa/Lagos' => 'NG', 'Africa/Lome' => 'TG', 'Africa/Abidjan' => 'CI',
        'Africa/Dakar' => 'SN', 'Africa/Ouagadougou' => 'BF', 'Africa/Bamako' => 'ML', 'Africa/Niamey' => 'NE',
        'Africa/Accra' => 'GH', 'Africa/Conakry' => 'GN', 'Africa/Nouakchott' => 'MR', 'Africa/Banjul' => 'GM',
        'Africa/Bissau' => 'GW', 'Africa/Freetown' => 'SL', 'Africa/Monrovia' => 'LR', 'Africa/Douala' => 'CM',
        'Africa/Libreville' => 'GA', 'Africa/Brazzaville' => 'CG', 'Africa/Kinshasa' => 'CD', 'Africa/Lubumbashi' => 'CD',
        'Africa/Bangui' => 'CF', 'Africa/Ndjamena' => 'TD', 'Africa/Malabo' => 'GQ', 'Africa/Kigali' => 'RW',
        'Africa/Bujumbura' => 'BI', 'Africa/Nairobi' => 'KE', 'Africa/Kampala' => 'UG', 'Africa/Dar_es_Salaam' => 'TZ',
        'Africa/Addis_Ababa' => 'ET', 'Africa/Johannesburg' => 'ZA', 'Africa/Casablanca' => 'MA', 'Africa/Algiers' => 'DZ',
        'Africa/Tunis' => 'TN', 'Africa/Cairo' => 'EG', 'Indian/Antananarivo' => 'MG', 'Indian/Mauritius' => 'MU',
        'Europe/Paris' => 'FR', 'Europe/Brussels' => 'BE', 'Europe/Zurich' => 'CH', 'Europe/London' => 'GB',
        'Europe/Berlin' => 'DE', 'America/New_York' => 'US', 'America/Chicago' => 'US', 'America/Los_Angeles' => 'US',
        'America/Toronto' => 'CA', 'America/Montreal' => 'CA',
    ];

    public static function depuis(?string $fuseau, ?string $langue): ?string
    {
        if (is_string($fuseau) && isset(self::FUSEAUX[$fuseau])) {
            return self::FUSEAUX[$fuseau];
        }

        if (is_string($langue) && preg_match('/^[a-z]{2,3}-([A-Z]{2})$/', $langue, $morceaux) === 1) {
            return $morceaux[1];
        }

        return null;
    }
}
