<?php

namespace App\Moteur\Exceptions;

use RuntimeException;

/** La nouvelle version n'a pas démarré : la copie est revenue à la précédente, avec ses données. */
class MiseAJourRetablie extends RuntimeException
{
    public function __construct(string $raison, public readonly ?string $sauvegarde)
    {
        parent::__construct($raison);
    }
}
