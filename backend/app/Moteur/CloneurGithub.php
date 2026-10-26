<?php

namespace App\Moteur;

use App\Moteur\Exceptions\DepotInvalide;
use Illuminate\Support\Facades\File;

class CloneurGithub
{
    /** Dépôt d'un inconnu : aucune configuration de la machine, aucune invite, messages en anglais. */
    public const ENV_GIT = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_LFS_SKIP_SMUDGE' => '1',
    ];

    /**
     * Clone le dépôt (branche de l'URL, ou $ref : branche ou tag), puis se place sur $commit s'il
     * est donné : une copie tourne sur le code de sa version, pas sur l'état du jour de la branche.
     */
    public function cloner(string $url, string $copie, ?string $commit = null, ?string $ref = null): string
    {
        $depot = DepotGithub::depuisUrl($url);
        if ($ref !== null && ! self::refValide($ref)) {
            throw new DepotInvalide('La branche, le tag ou le commit demandé est invalide.');
        }
        if ($commit !== null && preg_match('/^[0-9a-f]{40}$/', $commit) !== 1) {
            throw new DepotInvalide('Le commit demandé est invalide.');
        }
        $parent = config('moteur.copies');
        $destination = $parent.DIRECTORY_SEPARATOR.$copie;

        if (is_dir($destination)) {
            throw new DepotInvalide("La copie « {$copie} » existe déjà.");
        }

        File::ensureDirectoryExists($parent);

        // core.symlinks=false : un lien symbolique du dépôt (vers un .env, /etc…) devient un simple fichier.
        // Ni le validateur, ni docker compose (env_file, contexte de build) ne lisent alors hors du dépôt.
        $commande = ['git', 'clone', '--depth', '1', '--config', 'core.symlinks=false'];

        // Un commit en guise de ref se récupère après le clonage de la branche par défaut.
        $branche = $ref !== null && preg_match('/^[0-9a-f]{7,40}$/', $ref) !== 1 ? $ref : $depot->branche;
        if ($branche !== null) {
            $commande[] = '--branch';
            $commande[] = $branche;
            $commande[] = '--single-branch';
        }

        $commande[] = $this->miroir($depot) ?? $depot->urlClone;
        $commande[] = $destination;

        $resultat = Commande::isolee(self::ENV_GIT)
            ->timeout((int) config('moteur.git_timeout'))
            ->run($commande);

        if ($resultat->failed()) {
            File::deleteDirectory($destination);

            throw new DepotInvalide($ref !== null
                ? "Impossible de cloner le dépôt sur « {$ref} » : vérifiez que la branche ou le tag existe."
                : 'Impossible de cloner le dépôt GitHub public.');
        }

        $cible = $commit ?? ($ref !== null && preg_match('/^[0-9a-f]{7,40}$/', $ref) === 1 ? $ref : null);
        if ($cible !== null && ! $this->seRendreSur($destination, $cible)) {
            File::deleteDirectory($destination);

            throw new DepotInvalide("Le commit « {$cible} » est introuvable dans le dépôt.");
        }

        return $destination;
    }

    /** Commit sur lequel est le clone (40 caractères hexadécimaux). */
    public function commit(string $repertoire): string
    {
        $resultat = Commande::isolee(self::ENV_GIT)->path($repertoire)->timeout(30)->run(['git', 'rev-parse', 'HEAD']);
        $commit = trim($resultat->output());

        if ($resultat->failed() || preg_match('/^[0-9a-f]{40}$/', $commit) !== 1) {
            throw new DepotInvalide('Le commit du dépôt cloné est illisible.');
        }

        return $commit;
    }

    public static function refValide(string $ref): bool
    {
        return preg_match('#^[A-Za-z0-9._/-]{1,200}$#', $ref) === 1
            && ! str_contains($ref, '..') && ! str_starts_with($ref, '-') && ! str_starts_with($ref, '/') && ! str_ends_with($ref, '.lock');
    }

    /** Récupère un commit précis (GitHub accepte d'en servir un par son identifiant) et s'y place. */
    private function seRendreSur(string $repertoire, string $commit): bool
    {
        $git = fn (array $arguments) => Commande::isolee(self::ENV_GIT)->path($repertoire)->timeout((int) config('moteur.git_timeout'))->run(['git', ...$arguments]);

        $courant = trim($git(['rev-parse', 'HEAD'])->output());
        if (str_starts_with($courant, $commit)) {
            return true;
        }

        return $git(['fetch', '--depth', '1', 'origin', $commit])->successful()
            && $git(['checkout', '--quiet', '--detach', 'FETCH_HEAD'])->successful();
    }

    /**
     * Jeu de démo, en développement seulement : un dépôt local remplace le dépôt GitHub du même
     * nom (owner et repo sont déjà réduits à [A-Za-z0-9._-] par DepotGithub). Jamais en production.
     */
    private function miroir(DepotGithub $depot): ?string
    {
        $racine = config('moteur.miroirs');

        if (! is_string($racine) || $racine === '' || ! app()->environment('local', 'testing')) {
            return null;
        }

        $chemin = rtrim($racine, '/').'/'.$depot->owner.'/'.$depot->repo.'.git';

        return is_dir($chemin) && ! is_link($chemin) ? 'file://'.$chemin : null;
    }
}
