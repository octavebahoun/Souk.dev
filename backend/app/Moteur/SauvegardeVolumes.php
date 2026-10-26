<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\File;

/**
 * Sauvegarde des volumes d'une copie (projet Docker Compose « d{id} ») : chaque volume devient un
 * tar.gz dans storage/app/private/sauvegardes/{copie}/{horodatage}/. Un conteneur jetable de
 * l'image PostgreSQL déjà utilisée par le backend intégré lit ou écrit le volume, sans réseau ;
 * l'archive passe par une redirection du shell du moteur, jamais par la mémoire de PHP (un volume
 * peut peser des gigaoctets), et aucun chemin de la machine n'est monté dans le conteneur.
 */
class SauvegardeVolumes
{
    /** Volumes du projet, triés (une sauvegarde est reproductible). */
    public function volumes(string $copie): array
    {
        $resultat = Commande::isolee()->timeout(60)->run([
            'docker', 'volume', 'ls', '--quiet', '--filter', 'label=com.docker.compose.project='.$copie,
        ]);
        if ($resultat->failed()) {
            throw new LancementEchoue('Les volumes de la copie sont illisibles.');
        }

        $volumes = array_values(array_filter(array_map('trim', explode("\n", $resultat->output())), fn (string $nom) => preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,254}$/', $nom) === 1));
        sort($volumes);

        return $volumes;
    }

    /** Sauvegarde tous les volumes (conteneurs déjà arrêtés) et rend l'identifiant de la sauvegarde. */
    public function sauvegarder(string $copie): string
    {
        $identifiant = now()->format('Ymd-His');
        $dossier = $this->dossier($copie, $identifiant);
        File::ensureDirectoryExists($dossier);
        $volumes = $this->volumes($copie);

        foreach ($volumes as $volume) {
            $resultat = Commande::isolee()->timeout((int) config('moteur.sauvegarde_timeout', 900))->run([
                'sh', '-c', 'docker run --rm --network none -v "$1":/donnees:ro "$2" tar czf - -C /donnees . > "$3"',
                'sauvegarde', $volume, $this->image(), $dossier.DIRECTORY_SEPARATOR.$volume.'.tar.gz',
            ]);

            if ($resultat->failed()) {
                File::deleteDirectory($dossier);

                throw new LancementEchoue('La sauvegarde des données de la copie a échoué : rien n’a été modifié.');
            }
        }

        file_put_contents($dossier.DIRECTORY_SEPARATOR.'volumes.json', json_encode($volumes, JSON_THROW_ON_ERROR));

        return $identifiant;
    }

    /** Remet chaque volume dans l'état de la sauvegarde (conteneurs arrêtés). */
    public function restaurer(string $copie, string $identifiant): void
    {
        $dossier = $this->dossier($copie, $identifiant);
        $volumes = json_decode((string) @file_get_contents($dossier.DIRECTORY_SEPARATOR.'volumes.json'), true);
        if (! is_array($volumes)) {
            throw new LancementEchoue('La sauvegarde à restaurer est introuvable.');
        }

        foreach ($volumes as $volume) {
            $archive = $dossier.DIRECTORY_SEPARATOR.$volume.'.tar.gz';
            if (! is_string($volume) || ! is_file($archive)) {
                throw new LancementEchoue('Une archive de la sauvegarde est introuvable.');
            }

            $resultat = Commande::isolee()->timeout((int) config('moteur.sauvegarde_timeout', 900))->run([
                'sh', '-c', 'docker run --rm -i --network none -v "$1":/donnees "$2" sh -c "find /donnees -mindepth 1 -delete && tar xzf - -C /donnees" < "$3"',
                'restauration', $volume, $this->image(), $archive,
            ]);

            if ($resultat->failed()) {
                throw new LancementEchoue("Le volume {$volume} n'a pas pu être restauré.");
            }
        }
    }

    public function dossier(string $copie, string $identifiant): string
    {
        if (preg_match('/^d\d+$/', $copie) !== 1 || preg_match('/^\d{8}-\d{6}$/', $identifiant) !== 1) {
            throw new LancementEchoue('Sauvegarde inconnue.');
        }

        return rtrim((string) config('moteur.sauvegardes'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$copie.DIRECTORY_SEPARATOR.$identifiant;
    }

    /** Sauvegardes de plus de N jours retirées (la dernière de chaque copie est toujours gardée). */
    public function nettoyer(int $jours): int
    {
        $racine = (string) config('moteur.sauvegardes');
        $retirees = 0;

        foreach (File::directories($racine) as $copie) {
            $sauvegardes = File::directories($copie);
            sort($sauvegardes);
            array_pop($sauvegardes);

            foreach ($sauvegardes as $sauvegarde) {
                if (filemtime($sauvegarde) < now()->subDays($jours)->getTimestamp()) {
                    File::deleteDirectory($sauvegarde);
                    $retirees++;
                }
            }
        }

        return $retirees;
    }

    private function image(): string
    {
        return (string) config('moteur.backend_image_postgres', BackendIntegre::IMAGE_POSTGRES);
    }
}
