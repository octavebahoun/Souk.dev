<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\MiseAJourRetablie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applique une nouvelle version à une copie qui tourne :
 * 1. le nouveau code est préparé à côté (clone au commit, soukdev.json, docker-compose.yml, .env
 *    qui garde les secrets et les valeurs du client) : rien ne s'arrête s'il est refusé ;
 * 2. les conteneurs s'arrêtent, les volumes sont sauvegardés ;
 * 3. le nouveau code prend la place de l'ancien, les conteneurs sont reconstruits sur les mêmes
 *    volumes, les nouvelles migrations du backend intégré passent, la sonde doit répondre ;
 * 4. sinon, retour arrière : ancien code, données d'avant, anciens conteneurs, même sonde.
 */
class MiseAJourCopie
{
    public function __construct(
        private CloneurGithub $cloneur,
        private ValidateurSoukdev $validateur,
        private GenerateurEnv $generateur,
        private BackendIntegre $backend,
        private LanceurCompose $lanceur,
        private SondeServiceWeb $sonde,
        private RouteurCopies $routeur,
        private SauvegardeVolumes $sauvegardes,
    ) {}

    /**
     * @param  array<string, string>  $variablesClient
     * @return string l'identifiant de la sauvegarde prise avant la mise à jour
     *
     * @throws MiseAJourRetablie la nouvelle version n'a pas démarré, la copie tourne à nouveau sur l'ancienne
     * @throws LancementEchoue le nouveau code est refusé avant tout arrêt (rien n'a changé), ou le retour arrière a échoué
     */
    public function appliquer(string $copie, string $repertoire, string $depot, string $commit, array $variablesClient, string $taille): string
    {
        $nouveau = $this->preparer($copie, $repertoire, $depot, $commit, $variablesClient);
        $precedent = $repertoire.'-precedente';

        try {
            $this->lanceur->suspendre($repertoire, $copie);
            $sauvegarde = $this->sauvegardes->sauvegarder($copie);
        } catch (Throwable $erreur) {
            File::deleteDirectory($nouveau['repertoire']);
            $this->relancer($copie, $repertoire, $taille);

            throw new MiseAJourRetablie($erreur instanceof LancementEchoue ? $erreur->getMessage() : 'La sauvegarde des données a échoué : rien n’a été modifié.', null);
        }

        File::deleteDirectory($precedent);
        rename($repertoire, $precedent);
        rename($nouveau['repertoire'], $repertoire);

        try {
            $this->mettreEnLigne(new CopieLocale($copie, $repertoire, $nouveau['manifeste'], $nouveau['env'], false), $taille);
        } catch (Throwable $echec) {
            Log::warning('mise-a-jour: nouvelle version refusée, retour arrière', ['copie' => $copie, 'erreur' => class_basename($echec)]);
            $this->retablir($copie, $repertoire, $precedent, $sauvegarde, $taille);

            throw new MiseAJourRetablie($echec instanceof LancementEchoue ? $echec->getMessage() : 'La nouvelle version n’a pas démarré.', $sauvegarde);
        }

        File::deleteDirectory($precedent);

        return $sauvegarde;
    }

    /**
     * @param  array<string, string>  $variablesClient
     * @return array{repertoire: string, manifeste: array<string, mixed>, env: array<string, string>}
     */
    private function preparer(string $copie, string $repertoire, string $depot, string $commit, array $variablesClient): array
    {
        $nouveau = $this->cloneur->cloner($depot, $copie.'-maj', $commit);

        try {
            foreach (LanceurCompose::FICHIERS_RESERVES as $fichier) {
                $chemin = $nouveau.DIRECTORY_SEPARATOR.$fichier;
                if (is_file($chemin) || is_link($chemin)) {
                    unlink($chemin);
                }
            }

            $manifeste = $this->validateur->validerFichier($nouveau.DIRECTORY_SEPARATOR.'soukdev.json');
            $this->lanceur->verifier($nouveau, $manifeste['service_web']['nom']);
            $env = $this->generateur->reconduire($manifeste, $variablesClient, $this->generateur->lire($repertoire.DIRECTORY_SEPARATOR.'.env'));
            $env = $this->backend->reconduire($repertoire, $nouveau, $manifeste, $env);
            $this->generateur->ecrire($nouveau.DIRECTORY_SEPARATOR.'.env', $env);

            return ['repertoire' => $nouveau, 'manifeste' => $manifeste, 'env' => $env];
        } catch (Throwable $refus) {
            File::deleteDirectory($nouveau);

            throw $refus;
        }
    }

    /** Nouveaux conteneurs, migrations, sonde, route : la même mise en ligne qu'un premier déploiement, volumes gardés. */
    private function mettreEnLigne(CopieLocale $copie, string $taille): void
    {
        $this->lanceur->lancer($copie->repertoire, $copie->id, $taille);
        $this->backend->appliquer($copie);
        $this->sonder($copie->id, $copie->repertoire, $copie->manifeste);
    }

    /** Retour arrière : les fichiers et les données d'avant, puis les anciens conteneurs. */
    private function retablir(string $copie, string $repertoire, string $precedent, string $sauvegarde, string $taille): void
    {
        try {
            $this->lanceur->suspendre($repertoire, $copie);
        } catch (Throwable) {
            // Les nouveaux conteneurs n'ont peut-être jamais démarré.
        }

        File::deleteDirectory($repertoire);
        rename($precedent, $repertoire);
        $this->sauvegardes->restaurer($copie, $sauvegarde);
        $this->relancer($copie, $repertoire, $taille);
    }

    private function relancer(string $copie, string $repertoire, string $taille): void
    {
        $this->lanceur->lancer($repertoire, $copie, $taille);
        $this->sonder($copie, $repertoire, $this->validateur->validerFichier($repertoire.DIRECTORY_SEPARATOR.'soukdev.json'));
    }

    /**
     * @param  array<string, mixed>  $manifeste
     */
    private function sonder(string $copie, string $repertoire, array $manifeste): void
    {
        $nom = $manifeste['service_web']['nom'];
        $port = (int) $manifeste['service_web']['port'];
        $hote = $this->lanceur->adresseService($repertoire, $copie, $nom);
        $this->sonde->attendre($hote, $port, $copie.'_'.Durcissement::RESEAU);
        $this->routeur->publier($copie, $hote, $port);

        // Traefik relit ses routes par lots : la copie n'est annoncée qu'une fois joignable.
        $secondes = (int) config('moteur.delai_proxy', 0);
        if ($secondes > 0) {
            sleep($secondes);
        }
    }
}
