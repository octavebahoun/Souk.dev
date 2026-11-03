<?php

namespace App\Console\Commands;

use App\Jobs\RecalculerProfils;
use Illuminate\Console\Command;

class RecalculerProfilsCommande extends Command
{
    protected $signature = 'profils:recalculer';

    protected $description = 'Recalcule la copie rangée des profils (Souk Score de la recherche de devs) ; lancée au démarrage et chaque nuit';

    public function handle(): int
    {
        RecalculerProfils::dispatchSync();
        $this->info('Profils recalculés.');

        return self::SUCCESS;
    }
}
