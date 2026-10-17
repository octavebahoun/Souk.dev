<?php

declare(strict_types=1);

namespace App\Comptes;

/**
 * Chiffres du profil public tant qu'aucune action validée n'existe.
 * Stats, réputation et Souk Score seront calculés ici quand ces parties existeront.
 */
final class ProfilCalcule
{
    /**
     * @return array{bugs_resolus: int, applis_publiees: int, missions_terminees: int, note_moyenne: null, nb_notes: int}
     */
    public function stats(): array
    {
        return [
            'bugs_resolus' => 0,
            'applis_publiees' => 0,
            'missions_terminees' => 0,
            'note_moyenne' => null,
            'nb_notes' => 0,
        ];
    }

    /**
     * @return array{xp: int, niveau: string, badges: list<string>}
     */
    public function reputation(): array
    {
        return [
            'xp' => 0,
            'niveau' => 'debutant',
            'badges' => [],
        ];
    }

    /**
     * @return array{total: int, entraide: int, projets: int, clients: int, securite: int, activite: int}
     */
    public function soukScore(): array
    {
        return [
            'total' => 0,
            'entraide' => 0,
            'projets' => 0,
            'clients' => 0,
            'securite' => 0,
            'activite' => 0,
        ];
    }
}
