<?php

namespace App\Jobs;

use App\Comptes\ProfilCalcule;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Chaque nuit : l'activité récente du Souk Score glisse sur 30 jours et un
 * événement organisé finit par être passé, sans action de personne.
 */
class RecalculerProfils implements ShouldQueue
{
    use Queueable;

    public function handle(ProfilCalcule $profils): void
    {
        User::query()
            ->whereNotNull('username')
            ->orderBy('id')
            ->chunkById(200, function ($comptes) use ($profils): void {
                foreach ($comptes as $compte) {
                    $profils->rafraichir($compte);
                }
            });
    }
}
