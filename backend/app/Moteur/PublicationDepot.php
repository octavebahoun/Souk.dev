<?php

namespace App\Moteur;

use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\ManifestInvalide;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Contrôle un dépôt avant publication : clone, soukdev.json, docker-compose.yml.
 * La copie de contrôle est jetée ; la mesure relancera le dépôt à part.
 */
class PublicationDepot
{
    public function __construct(
        private CloneurGithub $cloneur,
        private ValidateurSoukdev $validateur,
        private LanceurCompose $lanceur,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lire(string $url): array
    {
        $copie = 'v'.bin2hex(random_bytes(4));
        $repertoire = null;

        try {
            $repertoire = $this->cloneur->cloner($url, $copie);
            $manifeste = $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
            $this->lanceur->verifier($repertoire, $manifeste['service_web']['nom']);

            return $manifeste;
        } catch (DepotInvalide|ManifestInvalide|LancementEchoue $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new DepotInvalide('Impossible de lire le dépôt GitHub public.');
        } finally {
            if (is_string($repertoire)) {
                File::deleteDirectory($repertoire);
            }
        }
    }
}
