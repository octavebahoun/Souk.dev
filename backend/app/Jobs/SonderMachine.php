<?php

namespace App\Jobs;

use App\Models\Machine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sonde de santé des machines, sur le moteur (seul à parler à Docker) : chaque démon répond-il,
 * avec quelle mémoire et quelle version ? Toutes les cinq minutes, ou une seule à la demande
 * de l'équipe. Une sonde ratée marque la machine injoignable : le placement l'évite. Une sonde
 * ciblée (à l'enregistrement, à la demande) qui échoue se relance deux fois, à dix secondes
 * d'écart : une machine qui démarre ne reste pas « injoignable » jusqu'au passage suivant.
 */
class SonderMachine implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Essais d'une sonde ciblée, celui-ci compris, avant d'attendre le passage planifié. */
    public const ESSAIS = 3;

    public function __construct(public ?int $machineId = null, public int $essai = 1)
    {
        $this->onQueue('moteur');
    }

    public function handle(): void
    {
        $machines = $this->machineId === null
            ? Machine::query()->orderBy('id')->get()
            : Machine::query()->whereKey($this->machineId)->get();

        foreach ($machines as $machine) {
            $sante = $machine->cible()->sonder();

            if ($sante->joignable) {
                $machine->forceFill([
                    'joignable' => true,
                    'version_docker' => $sante->versionDocker,
                    'memoire_totale_mo' => $sante->memoireTotaleMo,
                    'sonde_erreur' => null,
                    'sondee_le' => now(),
                ])->save();
            } else {
                $machine->forceFill([
                    'joignable' => false,
                    'sonde_erreur' => $sante->erreur,
                    'sondee_le' => now(),
                ])->save();

                if ($this->machineId !== null && $this->essai < self::ESSAIS) {
                    self::dispatch($machine->id, $this->essai + 1)->delay(now()->addSeconds(10));
                }
            }
        }
    }
}
