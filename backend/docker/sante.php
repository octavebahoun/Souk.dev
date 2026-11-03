<?php

/**
 * Contrôle de santé Docker des workers et du planificateur, sans démarrer Laravel :
 * php docker/sante.php worker|planificateur
 */

use App\Exploitation\Battements;

require __DIR__.'/../vendor/autoload.php';

$processus = $argv[1] ?? '';

if (! in_array($processus, ['worker', 'planificateur'], true)) {
    fwrite(STDERR, "Usage : php docker/sante.php worker|planificateur\n");
    exit(2);
}

exit((new Battements(__DIR__.'/../storage/framework/sante'))->estVivant($processus) ? 0 : 1);
