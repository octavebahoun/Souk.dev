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

    /** Clé refusée, solde épuisé, modèle ou paramètre rejeté : l'équipe doit intervenir. */
    public function concerneLEquipe(): bool
    {
        return in_array($this->statut, [400, 401, 402, 403, 404, 422], true);
    }
}
