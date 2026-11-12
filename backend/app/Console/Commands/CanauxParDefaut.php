<?php

namespace App\Console\Commands;

use App\Models\Canal;
use Illuminate\Console\Command;

/**
 * Ouvre les canaux de départ de l'Échange. Sans canal, personne ne peut publier de discussion :
 * la commande tourne à chaque démarrage et ne touche jamais un canal qui existe déjà.
 */
class CanauxParDefaut extends Command
{
    protected $signature = 'echange:canaux';

    protected $description = 'Ouvre les canaux de départ de l\'Échange s\'ils manquent';

    public function handle(): int
    {
        $ouverts = Canal::ouvrirParDefaut();

        $this->info($ouverts === 0 ? 'Les canaux de départ existent déjà.' : "{$ouverts} canaux de départ ouverts.");

        return self::SUCCESS;
    }
}
