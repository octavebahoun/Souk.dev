<?php

namespace App\Securite;

use App\Moteur\Commande;
use App\Moteur\Exceptions\LancementEchoue;
use JsonException;

/**
 * Sécurité avant publication (DECISIONS.md, ajout 10), dans des conteneurs jetables :
 * gitleaks (MIT) cherche les secrets, Trivy (Apache 2.0) les dépendances aux failles connues.
 * Le dépôt est copié dans le conteneur (docker cp) : aucun chemin de la machine n'y est monté,
 * et le conteneur n'a ni réseau (gitleaks) ni droit particulier. Seul le moteur parle à Docker.
 */
class AnalyseurDepot
{
    /** Gravités qui comptent pour le badge ; « LOW » et « UNKNOWN » feraient du bruit sur presque tout dépôt. */
    private const GRAVITES = ['MEDIUM' => 'moyenne', 'HIGH' => 'haute', 'CRITICAL' => 'critique'];

    /**
     * Secrets trouvés dans le dépôt (jamais le secret lui-même : gitleaks le masque).
     *
     * @return list<array{fichier: string, ligne: int, regle: string, description: string}>
     */
    public function secrets(string $repertoire): array
    {
        $sortie = $this->lancer((string) config('moteur.image_gitleaks'), [
            'dir', '/src', '--redact', '--no-banner', '--exit-code', '0', '--report-format', 'json', '--report-path', '/dev/stdout',
        ], $repertoire, ['--network', 'none'], 300);

        $secrets = [];

        foreach ($this->json($sortie) as $fuite) {
            if (! is_array($fuite)) {
                continue;
            }

            $secrets[] = [
                'fichier' => ltrim((string) preg_replace('#^/src/?#', '', (string) ($fuite['File'] ?? '')), '/'),
                'ligne' => (int) ($fuite['StartLine'] ?? 0),
                'regle' => (string) ($fuite['RuleID'] ?? 'secret'),
                'description' => mb_substr((string) ($fuite['Description'] ?? ''), 0, 200),
            ];
        }

        return $secrets;
    }

    /**
     * Dépendances aux failles connues, de gravité moyenne ou plus (format ProblemeSecurite du contrat).
     *
     * @return list<array{identifiant: string, paquet: string, version: string, gravite: string, version_corrigee: string|null}>
     */
    public function dependances(string $repertoire): array
    {
        // La base des failles se télécharge une fois dans un volume nommé, puis sert à chaque analyse.
        $sortie = $this->lancer((string) config('moteur.image_trivy'), [
            'fs', '--scanners', 'vuln', '--format', 'json', '--quiet', '--timeout', '10m', '/src',
        ], $repertoire, ['-v', config('moteur.volume_trivy').':/root/.cache/trivy'], 900);

        $rapport = $this->json($sortie);
        $problemes = [];

        foreach ((array) ($rapport['Results'] ?? []) as $resultat) {
            foreach ((array) ($resultat['Vulnerabilities'] ?? []) as $faille) {
                $gravite = self::GRAVITES[strtoupper((string) ($faille['Severity'] ?? ''))] ?? null;

                if ($gravite === null || ! is_string($faille['VulnerabilityID'] ?? null)) {
                    continue;
                }

                $cle = $faille['VulnerabilityID'].'|'.($faille['PkgName'] ?? '');
                $problemes[$cle] = [
                    'identifiant' => mb_substr($faille['VulnerabilityID'], 0, 60),
                    'paquet' => mb_substr((string) ($faille['PkgName'] ?? ''), 0, 120),
                    'version' => mb_substr((string) ($faille['InstalledVersion'] ?? ''), 0, 60),
                    'gravite' => $gravite,
                    'version_corrigee' => is_string($faille['FixedVersion'] ?? null) && $faille['FixedVersion'] !== '' ? mb_substr($faille['FixedVersion'], 0, 120) : null,
                ];
            }
        }

        $ordre = array_flip(['critique', 'haute', 'moyenne']);
        $problemes = array_values($problemes);
        usort($problemes, fn (array $a, array $b) => [$ordre[$a['gravite']], $a['paquet']] <=> [$ordre[$b['gravite']], $b['paquet']]);

        return $problemes;
    }

    /**
     * Conteneur jetable : créé, le dépôt copié dedans, lancé, puis supprimé quoi qu'il arrive.
     *
     * @param  list<string>  $arguments
     * @param  list<string>  $options
     */
    private function lancer(string $image, array $arguments, string $repertoire, array $options, int $delai): string
    {
        if (! is_dir($repertoire) || is_link($repertoire)) {
            throw new LancementEchoue('Le dépôt à analyser est introuvable.');
        }

        $creation = Commande::isolee()->timeout(120)->run([
            'docker', 'create', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges', '--pids-limit', '256', '--memory', '1g',
            ...$options, $image, ...$arguments,
        ]);

        $conteneur = trim($creation->output());

        if ($creation->failed() || preg_match('/^[a-f0-9]{12,64}$/', $conteneur) !== 1) {
            throw new LancementEchoue('Le conteneur d\'analyse n\'a pas pu être créé.');
        }

        try {
            $copie = Commande::isolee()->timeout(120)->run(['docker', 'cp', rtrim($repertoire, '/').'/.', $conteneur.':/src']);

            if ($copie->failed()) {
                throw new LancementEchoue('Le dépôt n\'a pas pu être copié pour l\'analyse.');
            }

            $analyse = Commande::isolee()->timeout($delai)->run(['docker', 'start', '-a', $conteneur]);

            if ($analyse->failed()) {
                throw new LancementEchoue('L\'analyse de sécurité a échoué.');
            }

            return $analyse->output();
        } finally {
            Commande::isolee()->timeout(60)->run(['docker', 'rm', '-f', $conteneur]);
        }
    }

    /**
     * @return array<mixed>
     */
    private function json(string $sortie): array
    {
        $sortie = trim($sortie);

        if ($sortie === '' || $sortie === 'null') {
            return [];
        }

        try {
            $donnees = json_decode($sortie, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LancementEchoue('Le rapport d\'analyse est illisible.');
        }

        return is_array($donnees) ? $donnees : [];
    }
}
