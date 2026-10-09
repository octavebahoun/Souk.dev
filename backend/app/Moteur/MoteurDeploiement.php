<?php

namespace App\Moteur;

use Illuminate\Support\Str;

class MoteurDeploiement
{
    public function __construct(
        private ValidateurSoukdev $validateur,
        private GenerateurEnv $generateur,
        private CloneurGithub $cloneur,
        private LanceurCompose $lanceur,
        private SondeServiceWeb $sonde,
    ) {}

    /**
     * @param  array<string, string>  $variablesClient
     */
    public function depuisGithub(string $url, array $variablesClient = [], ?string $copie = null, bool $dryRun = false, string $taille = 'petite'): CopieLocale
    {
        $depot = DepotGithub::depuisUrl($url);
        $copie ??= $this->nouveauId($depot->owner.'-'.$depot->repo);
        $this->lanceur->verifierNomCopie($copie);

        $repertoire = $this->cloneur->cloner($url, $copie);

        return $this->preparer($repertoire, $variablesClient, $copie, $dryRun, $taille);
    }

    /**
     * @param  array<string, string>  $variablesClient
     */
    public function depuisChemin(string $chemin, array $variablesClient = [], ?string $copie = null, bool $dryRun = false, string $taille = 'petite'): CopieLocale
    {
        $repertoire = realpath($chemin);

        if ($repertoire === false || ! is_dir($repertoire)) {
            throw new Exceptions\DepotInvalide("Le chemin « {$chemin} » n'existe pas.");
        }

        $copie ??= $this->nouveauId(basename($repertoire));
        $this->lanceur->verifierNomCopie($copie);

        return $this->preparer($repertoire, $variablesClient, $copie, $dryRun, $taille);
    }

    /**
     * @param  array<string, string>  $variablesClient
     */
    public function preparer(string $repertoire, array $variablesClient, string $copie, bool $dryRun = false, string $taille = 'petite'): CopieLocale
    {
        $manifeste = $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
        $this->lanceur->verifier($repertoire, $manifeste['service_web']['nom']);

        $env = $this->generateur->generer($manifeste, $variablesClient);
        $this->generateur->ecrire($repertoire.DIRECTORY_SEPARATOR.'.env', $env);

        $copieLocale = new CopieLocale(
            id: $copie,
            repertoire: $repertoire,
            manifeste: $manifeste,
            env: $env,
            lancee: false,
        );

        if ($dryRun) {
            return $copieLocale;
        }

        return $this->mettreEnLigne($copieLocale, $taille);
    }

    public function mettreEnLigne(CopieLocale $copie, string $taille = 'petite'): CopieLocale
    {
        $this->lanceur->lancer($copie->repertoire, $copie->id, $taille);

        try {
            $nom = $copie->manifeste['service_web']['nom'];
            $port = (int) $copie->manifeste['service_web']['port'];
            $hote = $this->lanceur->adresseService($copie->repertoire, $copie->id, $nom);
            $this->sonde->attendre($hote, $port);
        } catch (Exceptions\LancementEchoue $exception) {
            $this->lanceur->arreter($copie->repertoire, $copie->id);

            throw $exception;
        }

        return new CopieLocale(
            id: $copie->id,
            repertoire: $copie->repertoire,
            manifeste: $copie->manifeste,
            env: $copie->env,
            lancee: true,
        );
    }

    public function arreter(CopieLocale $copie): void
    {
        $this->lanceur->arreter($copie->repertoire, $copie->id);
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
