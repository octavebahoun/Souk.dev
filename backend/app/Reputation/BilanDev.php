<?php

declare(strict_types=1);

namespace App\Reputation;

/**
 * Ce qu'un dev a fait de validé par quelqu'un d'autre. Rien que des nombres :
 * c'est la seule entrée des calculs de réputation et de Souk Score.
 */
final readonly class BilanDev
{
    /**
     * @param  int  $applisSecurisees  Parmi les applis publiées, celles au badge Security Checked
     * @param  list<int>  $notesMissions  Note de 1 à 5 laissée par le client, une par mission terminée
     * @param  int  $evenementsPasses  Événements organisés, passés et non annulés
     * @param  int  $actionsRecentes  Actions validées sur les 30 derniers jours
     */
    public function __construct(
        public int $bugsResolus = 0,
        public int $applisPubliees = 0,
        public int $applisSecurisees = 0,
        public array $notesMissions = [],
        public int $evenementsPasses = 0,
        public int $actionsRecentes = 0,
    ) {}

    public function missionsTerminees(): int
    {
        return count($this->notesMissions);
    }

    public function noteMoyenne(): ?float
    {
        if ($this->notesMissions === []) {
            return null;
        }

        return array_sum($this->notesMissions) / count($this->notesMissions);
    }
}
