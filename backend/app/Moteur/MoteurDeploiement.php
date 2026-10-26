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
        private BackendIntegre $backend,
        private RouteurCopies $routeur,
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
        // Ces fichiers sont écrits par la plateforme. Venus du dépôt, ils seraient lancés sans vérification.
        foreach (LanceurCompose::FICHIERS_RESERVES as $fichier) {
            $chemin = $repertoire.DIRECTORY_SEPARATOR.$fichier;

            if (is_file($chemin) || is_link($chemin)) {
                unlink($chemin);
            }
        }

        $manifeste = $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
        $this->lanceur->verifier($repertoire, $manifeste['service_web']['nom']);

        $env = $this->backend->completerEnv(
            $repertoire,
            $manifeste,
            $this->generateur->generer($manifeste, $variablesClient),
        );
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
            $this->backend->appliquer($copie);

            $nom = $copie->manifeste['service_web']['nom'];
            $port = (int) $copie->manifeste['service_web']['port'];
            $hote = $this->lanceur->adresseService($copie->repertoire, $copie->id, $nom);
            $this->sonde->attendre($hote, $port, $copie->id.'_'.Durcissement::RESEAU);

            // Le sous-domaine ne s'ouvre qu'une fois le service web joignable, et vers lui seul.
            $this->routeur->publier($copie->id, $hote, $port);
            $this->attendreLeProxy();
        } catch (Exceptions\LancementEchoue $exception) {
            try {
                $this->lanceur->arreter($copie->repertoire, $copie->id);
            } catch (Exceptions\LancementEchoue) {
                // L'erreur de lancement reste celle à montrer ; le job libère la copie ensuite.
            }

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

    /**
     * Impayé : les conteneurs s'arrêtent, puis le sous-domaine affiche la page « copie suspendue ».
     * Dans cet ordre : si l'arrêt échoue, la copie reste en ligne avec sa route normale (le job
     * réessaie), au lieu d'une page « indisponible » devant une copie qui tourne.
     */
    public function suspendre(string $repertoire, string $copie): void
    {
        $this->lanceur->suspendre($repertoire, $copie);
        $this->routeur->publierPageSuspendue($copie);
    }

    /**
     * Paiement reçu : les mêmes conteneurs repartent avec leurs données ; le sous-domaine revient
     * vers le service web une fois celui-ci sondé (son adresse a pu changer au redémarrage).
     */
    public function reprendre(string $repertoire, string $copie): void
    {
        $manifeste = $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
        $this->lanceur->reprendre($repertoire, $copie);

        $nom = $manifeste['service_web']['nom'];
        $port = (int) $manifeste['service_web']['port'];
        $hote = $this->lanceur->adresseService($repertoire, $copie, $nom);
        $this->sonde->attendre($hote, $port, $copie.'_'.Durcissement::RESEAU);
        $this->routeur->publier($copie, $hote, $port);
        $this->attendreLeProxy();
    }

    /** Laisse à Traefik le temps de charger la route avant d'annoncer la copie en ligne. */
    private function attendreLeProxy(): void
    {
        $secondes = (int) (function_exists('config') ? config('moteur.delai_proxy', 0) : 0);

        if ($secondes > 0) {
            sleep($secondes);
        }
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
