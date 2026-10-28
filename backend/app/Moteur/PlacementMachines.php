<?php

namespace App\Moteur;

use App\Models\Machine;
use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\DB;

/**
 * Placement des copies : chaque nouvelle copie part sur la machine active et joignable qui a
 * le plus de mémoire libre. Une machine en maintenance (drain) ou injoignable ne reçoit rien ;
 * une machine sans limite connue passe devant (capacité inconnue, supposée libre).
 */
class PlacementMachines
{
    public function choisir(string $taille): Machine
    {
        $plafond = LimitesTaille::plafondMo($taille);

        return DB::transaction(function () use ($plafond): Machine {
            Machine::assurerLocale();

            // Deux lancements simultanés ne prennent pas la même dernière place.
            $machines = Machine::query()->orderBy('id')->lockForUpdate()->get();

            $elue = null;
            $mieux = null;

            foreach ($machines as $machine) {
                if (! $machine->recoitEncoreDesCopies()) {
                    continue;
                }

                $libre = $machine->memoireLibreMo();

                if ($libre !== null && $libre < $plafond) {
                    continue;
                }

                // Sans limite connue, la machine passe devant : on ne refuse pas une copie
                // faute d'avoir déclaré une capacité.
                if ($elue === null || $libre === null || ($mieux !== null && $libre > $mieux)) {
                    $elue = $machine;
                    $mieux = $libre;
                }
            }

            if ($elue === null) {
                throw new LancementEchoue('Aucune machine ne peut accueillir cette copie pour l’instant : toutes sont pleines, en maintenance ou injoignables.');
            }

            return $elue;
        });
    }
}
