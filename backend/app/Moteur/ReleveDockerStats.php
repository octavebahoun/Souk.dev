<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\Process;

/**
 * Pic du conteneur le plus gourmand d'une copie, lu avec docker stats.
 * Chaque service reçoit le même mem_limit : c'est ce conteneur qui atteint le plafond.
 */
final class ReleveDockerStats
{
    public function pic(string $repertoire, string $copie, int $dureeSecondes, int $intervalleSecondes): int
    {
        $echeance = microtime(true) + max(0, $dureeSecondes);
        $pic = null;

        do {
            $instantane = $this->picInstantane($repertoire, $copie);

            if ($instantane !== null) {
                $pic = $pic === null ? $instantane : max($pic, $instantane);
            }

            if (microtime(true) >= $echeance) {
                break;
            }

            $reste = (int) ceil($echeance - microtime(true));
            sleep(min(max(1, $intervalleSecondes), max(1, $reste)));
        } while (microtime(true) < $echeance);

        if ($pic === null) {
            throw new LancementEchoue('Aucun conteneur à mesurer.');
        }

        return $pic;
    }

    public function picInstantane(string $repertoire, string $copie): ?int
    {
        $ids = $this->identifiants($repertoire, $copie);

        if ($ids === []) {
            return null;
        }

        $resultat = Process::timeout(30)->run([
            'docker', 'stats', '--no-stream', '--format', '{{.MemUsage}}',
            ...$ids,
        ]);

        if ($resultat->failed()) {
            throw new LancementEchoue('Impossible de lire la mémoire des conteneurs.');
        }

        $pic = null;

        foreach (preg_split('/\R/', trim($resultat->output())) ?: [] as $ligne) {
            if ($ligne === '') {
                continue;
            }

            $mo = $this->moDepuisUsage($ligne);
            $pic = $pic === null ? $mo : max($pic, $mo);
        }

        if ($pic === null) {
            throw new LancementEchoue('Mesure mémoire illisible.');
        }

        return $pic;
    }

    public function moDepuisUsage(string $usage): int
    {
        $partie = trim(explode('/', $usage, 2)[0]);

        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*(B|KiB|MiB|GiB|TiB|kB|KB|MB|GB|TB)$/', $partie, $correspondance) !== 1) {
            throw new LancementEchoue('Mesure mémoire illisible.');
        }

        $nombre = (float) str_replace(',', '.', $correspondance[1]);
        $facteur = match (strtolower($correspondance[2])) {
            'b' => 1,
            'kib' => 1024,
            'kb' => 1000,
            'mib' => 1024 ** 2,
            'mb' => 1000 ** 2,
            'gib' => 1024 ** 3,
            'gb' => 1000 ** 3,
            'tib' => 1024 ** 4,
            'tb' => 1000 ** 4,
            default => throw new LancementEchoue('Mesure mémoire illisible.'),
        };

        return (int) ceil(($nombre * $facteur) / (1024 ** 2));
    }

    /**
     * @return list<string>
     */
    private function identifiants(string $repertoire, string $copie): array
    {
        (new LanceurCompose)->verifierNomCopie($copie);

        $resultat = Process::path($repertoire)
            ->timeout(30)
            ->run(['docker', 'compose', '-p', $copie, 'ps', '-q']);

        if ($resultat->failed()) {
            throw new LancementEchoue('Impossible de lister les conteneurs de la copie.');
        }

        $ids = [];

        foreach (preg_split('/\R/', trim($resultat->output())) ?: [] as $ligne) {
            $id = trim($ligne);

            if ($id === '') {
                continue;
            }

            if (preg_match('/^[a-f0-9]{12,64}$/', $id) !== 1) {
                throw new LancementEchoue('Identifiant de conteneur illisible.');
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
