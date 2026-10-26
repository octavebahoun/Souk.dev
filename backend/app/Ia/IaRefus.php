<?php

namespace App\Ia;

use RuntimeException;

/** Refus définitif (clé, solde, demande, réponse vide ou filtrée) : un nouvel essai n'y changerait rien. */
final class IaRefus extends RuntimeException
{
    public function __construct(public readonly int $statut, string $message)
    {
        parent::__construct($message);
    }

    /** La clé est refusée ou le solde épuisé : l'équipe doit intervenir. */
    public function concerneLEquipe(): bool
    {
        return in_array($this->statut, [401, 402, 403], true);
    }
}
