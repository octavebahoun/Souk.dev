<?php

namespace App\Moteur;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Supprime le dossier cloné d'une copie (code, .env et ses secrets), jamais
 * en dehors de la racine des copies : un chemin hors racine, la racine
 * elle-même ou un lien symbolique sont ignorés.
 */
final class DossierCopie
{
    public function __construct(private ?string $racine = null) {}

    public function racine(): ?string
    {
        $racine = $this->racine ?? (function_exists('config') ? config('moteur.copies') : null);

        if (! is_string($racine) || $racine === '' || is_link($racine)) {
            return null;
        }

        $reelle = realpath($racine);

        return $reelle === false || $reelle === DIRECTORY_SEPARATOR ? null : $reelle;
    }

    public function estDansLaRacine(string $chemin): bool
    {
        $racine = $this->racine();

        if ($racine === null || is_link($chemin) || ! is_dir($chemin)) {
            return false;
        }

        $cible = realpath($chemin);

        return $cible !== false && $cible !== $racine && str_starts_with($cible, $racine.DIRECTORY_SEPARATOR);
    }

    /**
     * @return bool Vrai si le dossier n'existe plus.
     */
    public function supprimer(?string $chemin): bool
    {
        if ($chemin === null || $chemin === '' || ! file_exists($chemin)) {
            return true;
        }

        if (! $this->estDansLaRacine($chemin)) {
            return false;
        }

        $cible = (string) realpath($chemin);
        $fichiers = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cible, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($fichiers as $fichier) {
            // Un lien est supprimé lui-même, sa cible n'est jamais suivie.
            if ($fichier->isLink() || ! $fichier->isDir()) {
                @unlink($fichier->getPathname());
            } else {
                @rmdir($fichier->getPathname());
            }
        }

        @rmdir($cible);

        return ! file_exists($cible);
    }

    /**
     * Dossiers d{id} présents dans la racine, indexés par id de déploiement.
     *
     * @return array<int, string>
     */
    public function dossiersDeDeploiement(): array
    {
        $racine = $this->racine();

        if ($racine === null) {
            return [];
        }

        $dossiers = [];

        foreach (scandir($racine) ?: [] as $nom) {
            $chemin = $racine.DIRECTORY_SEPARATOR.$nom;

            if (preg_match('/^d(\d+)$/', $nom, $id) === 1 && is_dir($chemin) && ! is_link($chemin)) {
                $dossiers[(int) $id[1]] = $chemin;
            }
        }

        return $dossiers;
    }
}
