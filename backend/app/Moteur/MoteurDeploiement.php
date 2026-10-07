<?php

namespace App\Moteur;

use Illuminate\Support\Str;

final class MoteurDeploiement
{
    public function __construct(
        private ValidateurSoukdev $validateur,
        private GenerateurEnv $generateur,
        private CloneurGithub $cloneur,
        private LanceurCompose $lanceur,
    ) {}

    /**
     * @param  array<string, string>  $variablesClient
     */
    public function depuisGithub(string $url, array $variablesClient = [], ?string $copie = null, bool $dryRun = false): CopieLocale
    {
        $depot = DepotGithub::depuisUrl($url);
        $copie ??= $this->nouveauId($depot->owner.'-'.$depot->repo);
        $this->lanceur->verifierNomCopie($copie);

        $repertoire = $this->cloneur->cloner($url, $copie);

        return $this->preparer($repertoire, $variablesClient, $copie, $dryRun);
    }

    /**
     * @param  array<string, string>  $variablesClient
     */
    public function depuisChemin(string $chemin, array $variablesClient = [], ?string $copie = null, bool $dryRun = false): CopieLocale
    {
        $repertoire = realpath($chemin);

        if ($repertoire === false || ! is_dir($repertoire)) {
            throw new Exceptions\DepotInvalide("Le chemin « {$chemin} » n'existe pas.");
        }

        $copie ??= $this->nouveauId(basename($repertoire));
        $this->lanceur->verifierNomCopie($copie);

        return $this->preparer($repertoire, $variablesClient, $copie, $dryRun);
    }

    /**
     * @param  array<string, string>  $variablesClient
     */
    public function preparer(string $repertoire, array $variablesClient, string $copie, bool $dryRun = false): CopieLocale
    {
        $manifeste = $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
        $this->lanceur->verifier($repertoire, $manifeste['service_web']['nom']);

        $env = $this->generateur->generer($manifeste, $variablesClient);
        $this->generateur->ecrire($repertoire.DIRECTORY_SEPARATOR.'.env', $env);

        $lancee = false;

        if (! $dryRun) {
            $this->lanceur->lancer($repertoire, $copie);
            $lancee = true;
        }

        return new CopieLocale(
            id: $copie,
            repertoire: $repertoire,
            manifeste: $manifeste,
            env: $env,
            lancee: $lancee,
        );
    }

    public function nouveauId(string $base): string
    {
        $slug = Str::slug($base);
        $suffixe = bin2hex(random_bytes(4));
        $id = ($slug !== '' ? $slug : 'copie').'-'.$suffixe;

        if (strlen($id) > 63) {
            $id = substr($slug, 0, 54).'-'.$suffixe;
        }

        return $id;
    }
}
