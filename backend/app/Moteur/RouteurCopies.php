<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;

/**
 * Reverse proxy : un sous-domaine par copie, vers son seul service web.
 *
 * Traefik lit un fichier de routes par copie, écrit ici par la plateforme
 * (fournisseur « file »). Il ne lit jamais les labels Docker : un dépôt ou une
 * image ne peut donc ni exposer un autre service, ni prendre le sous-domaine
 * d'une autre copie. Voir infra/proxy/.
 */
class RouteurCopies
{
    public function __construct(
        private string $dossier,
        private string $urlCopie,
    ) {}

    public static function depuisConfig(): self
    {
        return new self(
            (string) config('moteur.proxy_routes') ?: storage_path('app/private/proxy'),
            (string) config('moteur.url_copie'),
        );
    }

    public function url(string $copie): string
    {
        $this->verifierNomCopie($copie);

        return str_replace('{copie}', $copie, $this->urlCopie);
    }

    /**
     * Ouvre le sous-domaine de la copie vers l'adresse déjà sondée de son service web.
     */
    public function publier(string $copie, string $hote, int $port): string
    {
        $this->verifierNomCopie($copie);

        if (filter_var($hote, FILTER_VALIDATE_IP) === false) {
            throw new LancementEchoue('L\'adresse du service web est invalide.');
        }

        if ($port < 1 || $port > 65535) {
            throw new LancementEchoue('Le port du service web est invalide.');
        }

        $domaine = parse_url($this->url($copie), PHP_URL_HOST);

        if (! is_string($domaine) || preg_match('/^[a-z0-9.-]+$/', $domaine) !== 1) {
            throw new LancementEchoue('Le sous-domaine de la copie est invalide (voir MOTEUR_URL_COPIE).');
        }

        $cible = str_contains($hote, ':') ? "[{$hote}]" : $hote;
        $yaml = implode("\n", [
            'http:',
            '  routers:',
            "    {$copie}:",
            "      rule: \"Host(`{$domaine}`)\"",
            "      service: {$copie}",
            '  services:',
            "    {$copie}:",
            '      loadBalancer:',
            '        servers:',
            "          - url: \"http://{$cible}:{$port}\"",
        ])."\n";

        if (! is_dir($this->dossier) && ! @mkdir($this->dossier, 0755, true) && ! is_dir($this->dossier)) {
            throw new LancementEchoue('Impossible de créer le dossier des routes du proxy.');
        }

        // Écriture puis renommage : Traefik ne lit jamais un fichier à moitié écrit.
        $chemin = $this->chemin($copie);
        $temporaire = $chemin.'.tmp';

        if (file_put_contents($temporaire, $yaml) === false || ! rename($temporaire, $chemin)) {
            @unlink($temporaire);

            throw new LancementEchoue('Impossible d\'écrire la route de la copie.');
        }

        return $chemin;
    }

    public function retirer(string $copie): void
    {
        $this->verifierNomCopie($copie);

        $chemin = $this->chemin($copie);

        if (is_file($chemin) && ! unlink($chemin)) {
            throw new LancementEchoue('Impossible de retirer la route de la copie.');
        }
    }

    private function chemin(string $copie): string
    {
        return rtrim($this->dossier, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$copie.'.yml';
    }

    /** Même règle que Docker Compose, sans « _ », refusé dans un nom de domaine. */
    private function verifierNomCopie(string $copie): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $copie) !== 1) {
            throw new LancementEchoue('Le nom de la copie est invalide pour un sous-domaine.');
        }
    }
}
