<?php

namespace App\Jobs;

use App\Moteur\Commande;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Nettoyage hebdomadaire des images Docker du démon local : images et caches de construction
 * inutilisés depuis 7 jours. Les images des copies en ligne sont utilisées : elles ne partent
 * jamais. Sur le moteur (seul à parler à Docker) ; un échec est journalisé, jamais bloquant.
 */
class NettoyerImagesDocker implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ?int $ancienneteHeures = null)
    {
        $this->onQueue('moteur');
    }

    public function handle(): void
    {
        $heures = $this->ancienneteHeures ?? (int) config('exploitation.images_anciennete_heures', 168);

        foreach ([
            ['docker', 'image', 'prune', '-af', '--filter', "until={$heures}h"],
            ['docker', 'builder', 'prune', '-af', '--filter', "until={$heures}h"],
        ] as $commande) {
            $resultat = Commande::pour(null)->timeout(600)->run($commande);

            if ($resultat->failed()) {
                Log::warning('moteur: nettoyage des images impossible', ['commande' => $commande, 'erreur' => $resultat->errorOutput()]);
            }
        }
    }
}
