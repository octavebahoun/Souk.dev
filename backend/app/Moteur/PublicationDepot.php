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
        private RedacteurSoukdev $redacteur,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lire(string $url): array
    {
        return $this->verifier($url)['manifeste'];
    }

    /**
     * Les fichiers qui manquent à un dépôt pour être publié (soukdev.json, fichiers Docker), et la
     * branche où les ajouter. Un soukdev.json déjà présent et valide est gardé ; invalide, un
     * nouveau est proposé à sa place.
     *
     * @return array{manifeste: array<string, mixed>, fichiers: array<string, string>, deductions: list<string>, a_verifier: list<string>, soukdev: 'nouveau'|'garde'|'a_remplacer', branche: string|null}
     */
    public function rediger(string $url): array
    {
        $copie = 'r'.bin2hex(random_bytes(4));
        $repertoire = null;

        try {
            $repertoire = $this->cloneur->cloner($url, $copie);

            $chemin = $repertoire.DIRECTORY_SEPARATOR.'soukdev.json';
            $existant = null;
            $invalide = null;

            if (file_exists($chemin)) {
                if (file_exists($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml')) {
                    throw new DepotInvalide('Ce dépôt a déjà son soukdev.json et son docker-compose.yml : il ne manque rien, lancez « Vérifier le dépôt ».');
                }

                try {
                    $existant = $this->validateur->validerFichier($chemin);
                } catch (ManifestInvalide $exception) {
                    $invalide = $exception->getMessage();
                }
            }

            $redaction = $this->redacteur->rediger($repertoire, $existant);

            if ($invalide !== null) {
                array_unshift($redaction['a_verifier'], "Votre soukdev.json est invalide ({$invalide}) : remplacez-le par celui proposé ici.");
                $redaction['soukdev'] = 'a_remplacer';
            }
            // Filet : la proposition passe le même contrôle qu'un soukdev.json écrit à la main.
            $this->validateur->valider($redaction['manifeste']);

            return [...$redaction, 'branche' => $this->cloneur->branche($repertoire)];
        } catch (DepotInvalide|ManifestInvalide $exception) {
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

    /**
     * Le dépôt sur une branche, un tag ou un commit : son manifeste validé et le commit lu.
     *
     * @return array{manifeste: array<string, mixed>, commit: string}
     */
    public function verifier(string $url, ?string $ref = null, ?callable $aussi = null): array
    {
        $copie = 'v'.bin2hex(random_bytes(4));
        $repertoire = null;

        try {
            $repertoire = $this->cloneur->cloner($url, $copie, ref: $ref);
            $manifeste = $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
            $this->lanceur->verifier($repertoire, $manifeste['service_web']['nom']);
            $commit = $this->cloneur->commit($repertoire);
            // Contrôle supplémentaire sur le même clone (recherche de secrets d'une nouvelle version).
            if ($aussi !== null) {
                $aussi($repertoire);
            }

            return ['manifeste' => $manifeste, 'commit' => $commit];
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
