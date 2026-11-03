<?php

namespace App\Ia;

/**
 * Fournisseur simulé (tests, développement hors ligne) : une analyse courte, marquée comme telle.
 * « [ia-indisponible] » ou « [ia-refus] » dans le bug simulent une panne ou un refus.
 */
final class IaSimulee implements FournisseurIa
{
    public function analyser(string $consigne, string $demande): ReponseIa
    {
        if (str_contains($demande, '[ia-indisponible]')) {
            throw new IaIndisponible('Fournisseur simulé indisponible.');
        }

        if (str_contains($demande, '[ia-refus]')) {
            throw new IaRefus(200, 'Refus simulé.');
        }

        return new ReponseIa(
            texte: "**Analyse simulée** (développement : aucun appel à DeepSeek).\n\n"
                ."**Cause probable** : la valeur lue est vide au moment où le code l'utilise.\n\n"
                ."**Piste** : vérifiez la donnée reçue avant de l'utiliser, puis rejouez le cas qui échoue.",
            modele: 'simule',
            jetonsEntree: mb_strlen($demande),
            jetonsSortie: 60,
        );
    }
}
