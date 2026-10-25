<?php

namespace App\Moteur;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

/** Point de départ de toute commande git ou docker du moteur : environnement minimal, jamais hérité. */
final class Commande
{
    /**
     * @param  array<string, string>  $ajouts
     */
    public static function isolee(array $ajouts = []): PendingProcess
    {
        return Process::env(EnvironnementProcessus::minimal($ajouts));
    }
}
