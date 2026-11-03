<?php

namespace App\Jobs;

use App\Models\Machine;
use App\Moteur\Commande;
use App\Moteur\MachineCible;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Nettoyage hebdomadaire des images Docker, sur le démon local puis sur chaque démon
 * distant joignable : images et caches de construction inutilisés depuis 7 jours. Les
 * images des copies en ligne sont utilisées : elles ne partent jamais. Sur le moteur
 * (seul à parler à Docker) ; un échec est journalisé, jamais bloquant.
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

        // Le démon local toujours, puis chaque distant joignable (la ligne « locale »,
        // sans hôte, est le démon local : déjà fait). Les injoignables sont laissés à
        // la sonde : un prune vers un démon mort attendrait le délai en pure perte.
        $cibles = collect([null]);
        Machine::query()->where('joignable', true)->whereNotNull('hote_docker')->orderBy('id')->each(
            fn (Machine $machine) => $cibles->push($machine->cible())
        );

        $commandes = [
            ['docker', 'image', 'prune', '-af', '--filter', "until={$heures}h"],
            ['docker', 'builder', 'prune', '-af', '--filter', "until={$heures}h"],
        ];

        /** @var MachineCible|null $cible */
        foreach ($cibles as $cible) {
            foreach ($commandes as $commande) {
                $resultat = Commande::pour($cible)->timeout(600)->run($commande);

                if ($resultat->failed()) {
                    Log::warning('moteur: nettoyage des images impossible', ['commande' => $commande, 'erreur' => $resultat->errorOutput()]);
                }
            }
        }
    }
}
