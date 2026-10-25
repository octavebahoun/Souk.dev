<?php

namespace Tests\Feature\Moteur;

use App\Moteur\Durcissement;

/**
 * Imite la sortie de docker compose config une fois docker-compose.soukdev.yml fusionné.
 */
trait ConfigDurcie
{
    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private static function configDurcie(array $document, string $taille = 'petite'): array
    {
        foreach ($document['services'] as $nom => $service) {
            $document['services'][$nom] = $service + [
                'pids_limit' => Durcissement::pids($taille),
                'cap_drop' => ['ALL'],
                'cap_add' => Durcissement::capacites(),
                'security_opt' => [Durcissement::SECURITE],
                'networks' => [Durcissement::RESEAU => null],
            ];
        }

        // Docker nomme le réseau <projet>_soukdev-copie ; LanceurCompose retire ce nom avant la vérification.
        $reseau = ['driver' => 'bridge'];

        if (is_string($document['name'] ?? null)) {
            $reseau['name'] = $document['name'].'_'.Durcissement::RESEAU;
        }

        $document['networks'] = ($document['networks'] ?? []) + [Durcissement::RESEAU => $reseau];

        return $document;
    }
}
