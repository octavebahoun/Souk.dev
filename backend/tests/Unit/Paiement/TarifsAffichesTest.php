<?php

namespace Tests\Unit\Paiement;

use Tests\TestCase;

/**
 * Les prix d'hébergement affichés par le front (formules.ts) sont ceux que le serveur facture
 * (config/facturation.php) : une seule vérité, vérifiée à chaque passage des tests.
 */
class TarifsAffichesTest extends TestCase
{
    public function test_le_front_affiche_les_prix_que_le_serveur_facture(): void
    {
        $fichier = base_path('../frontend/src/features/paiements/formules.ts');

        if (! is_file($fichier)) {
            $this->markTestSkipped('Le front n\'est pas à côté du backend (image Docker de production).');
        }

        $source = (string) file_get_contents($fichier);

        foreach ((array) config('facturation.hebergement') as $taille => $prix) {
            $this->assertMatchesRegularExpression(
                "/{$taille}: \\{[^}]*prix: {$prix},/",
                $source,
                "formules.ts n'affiche pas {$prix} F pour la formule {$taille}.",
            );
        }
    }
}
