<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Lance une copie jetable au plafond « grande », relève son pic, puis l'arrête.
 */
class MesureMemoire
{
    public function __construct(
        private MoteurDeploiement $moteur,
        private CloneurGithub $cloneur,
        private ValidateurSoukdev $validateur,
        private LanceurCompose $lanceur,
        private ReleveDockerStats $releve,
    ) {}

    public function pic(string $depotUrl, string $copie): int
    {
        $this->lanceur->verifierNomCopie($copie);
        $repertoire = null;

        try {
            $repertoire = $this->cloneur->cloner($depotUrl, $copie);
            $manifeste = $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
            $copieLocale = $this->moteur->preparer(
                $repertoire,
                $this->variablesClient($manifeste),
                $copie,
                true,
                'grande',
            );
            $this->moteur->mettreEnLigne($copieLocale, 'grande');

            return $this->releve->pic(
                $repertoire,
                $copie,
                (int) config('moteur.mesure_duree'),
                (int) config('moteur.mesure_intervalle'),
            );
        } finally {
            if (is_string($repertoire)) {
                try {
                    $this->lanceur->arreter($repertoire, $copie);
                } catch (Throwable) {
                    // La copie est déjà arrêtée.
                }

                File::deleteDirectory($repertoire);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $manifeste
     * @return array<string, string>
     */
    private function variablesClient(array $manifeste): array
    {
        $variables = [];

        if (! isset($manifeste['variables']) || ! is_array($manifeste['variables'])) {
            throw new LancementEchoue('soukdev.json ne déclare aucune variable.');
        }

        foreach ($manifeste['variables'] as $variable) {
            if (! is_array($variable) || ($variable['source'] ?? null) !== 'client') {
                continue;
            }

            if (($variable['requis'] ?? false) === true && is_string($variable['nom'] ?? null)) {
                $variables[$variable['nom']] = 'soukdev';
            }
        }

        return $variables;
    }
}
