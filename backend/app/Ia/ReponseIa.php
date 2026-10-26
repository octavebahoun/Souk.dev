<?php

namespace App\Ia;

/** Réponse d'un fournisseur : le texte (Markdown), le modèle qui l'a écrit et sa consommation. */
final readonly class ReponseIa
{
    public function __construct(
        public string $texte,
        public string $modele,
        public int $jetonsEntree = 0,
        public int $jetonsSortie = 0,
    ) {}
}
