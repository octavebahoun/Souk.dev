<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;

/**
 * Fichiers .sql d'un backend intégré, dans l'ordre des noms, sans sortir du dépôt.
 */
final class FichiersMigration
{
    private const TAILLE_MAX = 1_000_000;

    private const NOMBRE_MAX = 50;

    /**
     * @return list<string>
     */
    public function lister(string $repertoire, string $cheminRelatif): array
    {
        $racine = realpath($repertoire);

        if ($racine === false || ! is_dir($racine)) {
            throw new LancementEchoue('Le dépôt de la copie est introuvable.');
        }

        if ($cheminRelatif === '' || str_starts_with($cheminRelatif, '/') || str_contains($cheminRelatif, '..')) {
            throw new LancementEchoue('Le chemin des migrations est refusé.');
        }

        $dossier = $racine.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $cheminRelatif);
        $resolu = realpath($dossier);

        if ($resolu === false || ! is_dir($resolu)) {
            throw new LancementEchoue('Le dossier des migrations est introuvable.');
        }

        if ($resolu !== $racine && ! str_starts_with($resolu.DIRECTORY_SEPARATOR, $racine.DIRECTORY_SEPARATOR)) {
            throw new LancementEchoue('Le dossier des migrations sort du dépôt.');
        }

        $fichiers = glob($resolu.DIRECTORY_SEPARATOR.'*.sql') ?: [];
        sort($fichiers, SORT_STRING);

        $propres = [];

        foreach ($fichiers as $fichier) {
            if (! is_file($fichier)) {
                continue;
            }

            $reel = realpath($fichier);

            if ($reel === false || ! str_starts_with($reel, $resolu.DIRECTORY_SEPARATOR)) {
                throw new LancementEchoue('Une migration sort du dossier déclaré.');
            }

            $this->verifierContenu($reel);
            $propres[] = $reel;
        }

        if ($propres === []) {
            throw new LancementEchoue('Aucune migration .sql dans le dossier déclaré.');
        }

        if (count($propres) > self::NOMBRE_MAX) {
            throw new LancementEchoue('Le dossier des migrations dépasse 50 fichiers.');
        }

        return $propres;
    }

    private function verifierContenu(string $fichier): void
    {
        $taille = filesize($fichier);

        if ($taille === false || $taille > self::TAILLE_MAX) {
            throw new LancementEchoue(basename($fichier).' dépasse 1 Mo.');
        }

        $contenu = file_get_contents($fichier);

        if ($contenu === false || str_contains($contenu, "\0")) {
            throw new LancementEchoue(basename($fichier).' est illisible.');
        }

        foreach (preg_split('/\R/', $contenu) ?: [] as $ligne) {
            if (str_starts_with(ltrim($ligne), '\\')) {
                throw new LancementEchoue(basename($fichier).' contient une commande psql, pas seulement du SQL.');
            }
        }
    }
}
