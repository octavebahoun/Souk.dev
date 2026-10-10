<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;

/**
 * Plus petite formule qui laisse 20 % de libre sous son plafond.
 * 450 Mo dépasse 80 % de 512 Mo : la recommandation est moyenne.
 */
final class RecommandationTaille
{
    public function depuisPic(int $picMo): string
    {
        if ($picMo < 0) {
            throw new LancementEchoue('Le pic de mémoire est invalide.');
        }

        foreach (['petite', 'moyenne', 'grande'] as $taille) {
            if ($taille === 'grande' || $picMo <= $this->seuil($taille)) {
                return $taille;
            }
        }

        return 'grande';
    }

    private function seuil(string $taille): int
    {
        return (int) floor(LimitesTaille::plafondMo($taille) * 0.8);
    }
}
