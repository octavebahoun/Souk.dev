<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;

/**
 * Refuse un docker-compose.yml dangereux avant tout lancement.
 * Réutilisable plus tard sur la vérification et la publication d'une appli.
 */
final class VerificateurCompose
{
    /** Clés de premier niveau acceptées (plus les extensions x-*). */
    private const CLES_RACINE = ['services', 'volumes', 'networks', 'name', 'version'];

    /**
     * Clés d'un service acceptées (plus les extensions x-*). Tout le reste est refusé :
     * cap_add, devices, pid, ipc, userns_mode, security_opt, sysctls, volumes_from,
     * container_name, secrets, configs, runtime…
     */
    private const CLES_SERVICE = [
        'image', 'build', 'command', 'entrypoint', 'environment', 'env_file',
        'depends_on', 'volumes', 'tmpfs', 'expose', 'ports', 'networks', 'network_mode',
        'privileged', 'restart', 'healthcheck', 'working_dir', 'user', 'labels',
        'hostname', 'domainname', 'init', 'tty', 'stdin_open', 'stop_signal',
        'stop_grace_period', 'read_only', 'deploy', 'mem_limit', 'mem_reservation',
        'memswap_limit', 'cpus', 'cpu_shares', 'cap_drop', 'links', 'platform',
        'pull_policy', 'profiles', 'extra_hosts', 'dns', 'dns_search', 'annotations',
        'attach', 'shm_size',
    ];

    /** Clés acceptées dans la forme longue de build. */
    private const CLES_BUILD = ['context', 'dockerfile', 'args', 'target', 'labels'];

    public function verifier(string $yaml): void
    {
        $document = $this->document($yaml);

        if (array_key_exists('include', $document)) {
            throw new LancementEchoue('docker-compose.yml utilise include et ne peut pas être vérifié.');
        }

        foreach (array_keys($document) as $cle) {
            if (! $this->cleAutorisee((string) $cle, self::CLES_RACINE)) {
                throw new LancementEchoue("docker-compose.yml utilise « {$cle} », qui n'est pas autorisé.");
            }
        }

        $services = $document['services'] ?? null;

        if (! is_array($services) || array_is_list($services)) {
            throw new LancementEchoue('docker-compose.yml ne déclare aucun service.');
        }

        $erreurs = [];

        foreach ($services as $nom => $service) {
            $nom = (string) $nom;

            if (! is_array($service) || array_is_list($service)) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            if (array_key_exists('extends', $service)) {
                throw new LancementEchoue("Le service « {$nom} » utilise extends et ne peut pas être vérifié.");
            }

            foreach (array_keys($service) as $cle) {
                if (! $this->cleAutorisee((string) $cle, self::CLES_SERVICE)) {
                    $erreurs[] = "Le service « {$nom} » utilise « {$cle} », qui n'est pas autorisé.";
                }
            }

            $this->verifierPrivileged($nom, $service, $erreurs);
            $this->verifierNetworkMode($nom, $service, $erreurs);
            $this->verifierPorts($nom, $service, $erreurs);
            $this->verifierVolumesService($nom, $service, $erreurs);
            $this->verifierBuild($nom, $service, $erreurs);
            $this->verifierEnvFile($nom, $service, $erreurs);
        }

        $this->verifierVolumesRacine($document['volumes'] ?? null, $erreurs);
        $this->verifierReseauxRacine($document['networks'] ?? null, $erreurs);

        if ($erreurs !== []) {
            throw new LancementEchoue("docker-compose.yml est dangereux :\n".implode("\n", $erreurs));
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierPrivileged(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('privileged', $service) || $service['privileged'] === null) {
            return;
        }

        if ($this->estVrai($service['privileged'])) {
            $erreurs[] = "Le service « {$nom} » est privileged.";

            return;
        }

        if (! $this->estFaux($service['privileged'])) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierNetworkMode(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('network_mode', $service) || $service['network_mode'] === null) {
            return;
        }

        if (! is_string($service['network_mode'])) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        $mode = strtolower($service['network_mode']);

        if ($mode === 'host') {
            $erreurs[] = "Le service « {$nom} » utilise network_mode host.";

            return;
        }

        // container:… et service:… feraient entrer la copie dans le réseau d'un autre conteneur.
        if (! in_array($mode, ['bridge', 'none'], true)) {
            $erreurs[] = "Le service « {$nom} » utilise network_mode « {$service['network_mode']} », qui n'est pas autorisé.";
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierPorts(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('ports', $service) || $service['ports'] === null || $service['ports'] === '' || $service['ports'] === []) {
            return;
        }

        $ports = $service['ports'];

        if (is_string($ports) || is_int($ports)) {
            $erreurs[] = "Le service « {$nom} » publie des ports.";

            return;
        }

        if (! is_array($ports) || ! array_is_list($ports)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        if ($ports !== []) {
            $erreurs[] = "Le service « {$nom} » publie des ports.";
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierVolumesService(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('volumes', $service) || $service['volumes'] === null) {
            return;
        }

        $volumes = $service['volumes'];

        if (is_string($volumes)) {
            $volumes = [$volumes];
        }

        if (! is_array($volumes) || ! array_is_list($volumes)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        foreach ($volumes as $volume) {
            // Une variable ${…} serait remplacée par Docker au lancement, après notre vérification.
            if ($this->contientVariable($volume)) {
                $erreurs[] = "Le service « {$nom} » utilise une variable dans un volume.";

                continue;
            }

            if (is_array($volume) && isset($volume['type']) && ! in_array(strtolower((string) $volume['type']), ['volume', 'tmpfs', 'bind', 'npipe'], true)) {
                $erreurs[] = "Le service « {$nom} » utilise un volume de type « {$volume['type']} », qui n'est pas autorisé.";

                continue;
            }

            $source = $this->sourceHote($volume);

            if ($source !== null) {
                $erreurs[] = "Le service « {$nom} » monte un volume de l'hôte « {$source} ».";
            }
        }
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function verifierVolumesRacine(mixed $volumes, array &$erreurs): void
    {
        if ($volumes === null) {
            return;
        }

        if (! is_array($volumes) || array_is_list($volumes)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        foreach ($volumes as $nom => $definition) {
            if ($definition === null) {
                continue;
            }

            if (! is_array($definition) || array_is_list($definition)) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            // external ou name pourraient viser le volume d'une autre copie ou de la plateforme.
            if (array_key_exists('external', $definition) && ! $this->estFaux($definition['external'])) {
                $erreurs[] = "Le volume « {$nom} » est externe.";
            }

            if (array_key_exists('name', $definition)) {
                $erreurs[] = "Le volume « {$nom} » fixe son nom (name), ce qui n'est pas autorisé.";
            }

            if ($this->contientVariable($definition)) {
                $erreurs[] = "Le volume « {$nom} » utilise une variable.";
            }

            $options = $definition['driver_opts'] ?? null;

            if ($options === null) {
                continue;
            }

            if (! is_array($options) || array_is_list($options)) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            $type = strtolower((string) ($options['type'] ?? ''));
            $option = strtolower((string) ($options['o'] ?? ''));
            $device = (string) ($options['device'] ?? '');

            if ($type === 'bind' || str_contains($option, 'bind') || $this->estCheminHote($device)) {
                $cible = $device !== '' ? " « {$device} »" : '';
                $erreurs[] = "Le volume « {$nom} » monte un chemin de l'hôte{$cible}.";
            }
        }
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function verifierReseauxRacine(mixed $reseaux, array &$erreurs): void
    {
        if ($reseaux === null) {
            return;
        }

        if (! is_array($reseaux) || array_is_list($reseaux)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        foreach ($reseaux as $nom => $definition) {
            if ($definition === null) {
                continue;
            }

            if (! is_array($definition) || array_is_list($definition)) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            // Un réseau externe ou nommé ferait sortir la copie de son propre projet.
            if (array_key_exists('external', $definition) && ! $this->estFaux($definition['external'])) {
                $erreurs[] = "Le réseau « {$nom} » est externe.";
            }

            if (array_key_exists('name', $definition)) {
                $erreurs[] = "Le réseau « {$nom} » fixe son nom (name), ce qui n'est pas autorisé.";
            }

            $pilote = $definition['driver'] ?? null;

            if ($pilote !== null && strtolower((string) $pilote) !== 'bridge') {
                $erreurs[] = "Le réseau « {$nom} » utilise le pilote « {$pilote} », qui n'est pas autorisé.";
            }

            if (array_key_exists('driver_opts', $definition) || $this->contientVariable($definition)) {
                $erreurs[] = "Le réseau « {$nom} » utilise des options qui ne sont pas autorisées.";
            }
        }
    }

    /**
     * build doit rester dans le dépôt : un contexte « / » copierait les fichiers du serveur dans l'image.
     *
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierBuild(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('build', $service) || $service['build'] === null) {
            return;
        }

        $build = $service['build'];

        if (is_string($build)) {
            $build = ['context' => $build];
        }

        if (! is_array($build) || array_is_list($build)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        foreach (array_keys($build) as $cle) {
            if (! in_array((string) $cle, self::CLES_BUILD, true)) {
                $erreurs[] = "Le service « {$nom} » utilise build.{$cle}, qui n'est pas autorisé.";
            }
        }

        foreach (['context', 'dockerfile'] as $cle) {
            if (isset($build[$cle]) && ! $this->cheminDansDepot($build[$cle])) {
                $erreurs[] = "Le service « {$nom} » utilise un build.{$cle} hors du dépôt.";
            }
        }
    }

    /**
     * env_file lirait un fichier du serveur s'il pointait hors du dépôt.
     *
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierEnvFile(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('env_file', $service) || $service['env_file'] === null) {
            return;
        }

        $fichiers = is_array($service['env_file']) ? $service['env_file'] : [$service['env_file']];

        if (! array_is_list($fichiers)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        foreach ($fichiers as $fichier) {
            $chemin = is_array($fichier) ? ($fichier['path'] ?? null) : $fichier;

            if (! $this->cheminDansDepot($chemin)) {
                $erreurs[] = "Le service « {$nom} » lit un env_file hors du dépôt.";
            }
        }
    }

    private function cheminDansDepot(mixed $chemin): bool
    {
        if (! is_string($chemin) || $chemin === '' || $this->contientVariable($chemin)) {
            return false;
        }

        if (
            str_starts_with($chemin, '/')
            || str_starts_with($chemin, '~')
            || str_contains($chemin, '://')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $chemin) === 1
        ) {
            return false;
        }

        foreach (preg_split('#[\\\\/]#', $chemin) ?: [] as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function contientVariable(mixed $valeur): bool
    {
        if (is_string($valeur)) {
            return str_contains($valeur, '$');
        }

        if (is_array($valeur)) {
            foreach ($valeur as $cle => $element) {
                if ($this->contientVariable((string) $cle) || $this->contientVariable($element)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $autorisees
     */
    private function cleAutorisee(string $cle, array $autorisees): bool
    {
        return in_array($cle, $autorisees, true) || str_starts_with($cle, 'x-');
    }

    private function sourceHote(mixed $volume): ?string
    {
        if (is_string($volume)) {
            return $this->sourceHoteCourt($volume);
        }

        if (! is_array($volume) || array_is_list($volume)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        $type = isset($volume['type']) ? strtolower((string) $volume['type']) : null;
        $source = isset($volume['source']) ? (string) $volume['source'] : '';

        if ($type === 'bind' || $type === 'npipe') {
            return $source !== '' ? $source : $type;
        }

        if ($this->estCheminHote($source)) {
            return $source;
        }

        $options = $volume['driver_opts'] ?? null;

        if (is_array($options)) {
            $device = (string) ($options['device'] ?? '');
            $option = strtolower((string) ($options['o'] ?? ''));

            if (str_contains($option, 'bind') || $this->estCheminHote($device)) {
                return $device !== '' ? $device : 'bind';
            }
        }

        return null;
    }

    private function sourceHoteCourt(string $spec): ?string
    {
        $spec = trim($spec);

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $spec) === 1) {
            $source = preg_split('/:(?=[^\\\\\\/])/', $spec, 2)[0] ?? $spec;

            return $source !== '' ? $source : $spec;
        }

        $parties = explode(':', $spec);

        if (count($parties) < 2) {
            return null;
        }

        return $this->estCheminHote($parties[0]) ? $parties[0] : null;
    }

    private function estCheminHote(string $source): bool
    {
        if ($source === '') {
            return false;
        }

        return $source === '.'
            || $source === '..'
            || str_starts_with($source, '/')
            || str_starts_with($source, '.')
            || str_starts_with($source, '~')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $source) === 1;
    }

    private function estVrai(mixed $valeur): bool
    {
        if (is_bool($valeur)) {
            return $valeur;
        }

        if (is_int($valeur)) {
            return $valeur === 1;
        }

        return is_string($valeur) && in_array(strtolower($valeur), ['true', 'yes', 'on', '1'], true);
    }

    private function estFaux(mixed $valeur): bool
    {
        if (is_bool($valeur)) {
            return ! $valeur;
        }

        if (is_int($valeur)) {
            return $valeur === 0;
        }

        return is_string($valeur) && in_array(strtolower($valeur), ['false', 'no', 'off', '0'], true);
    }

    /**
     * @return array<mixed>
     */
    private function document(string $yaml): array
    {
        if (str_contains($yaml, "\t")) {
            throw new LancementEchoue('docker-compose.yml utilise des tabulations et ne peut pas être vérifié.');
        }

        if (preg_match('/<<:|(^|[\s\[{,])&[A-Za-z_]|(^|[\s\[{,])\*[A-Za-z_]/', $yaml) === 1) {
            throw new LancementEchoue('docker-compose.yml utilise une ancre YAML et ne peut pas être vérifié.');
        }

        $lignes = [];

        foreach (preg_split('/\R/', $yaml) ?: [] as $brute) {
            $sans = $this->sansCommentaire($brute);

            if (trim($sans) === '' || trim($sans) === '---' || trim($sans) === '...') {
                continue;
            }

            preg_match('/^( *)/', $sans, $espaces);
            $lignes[] = [
                'indent' => strlen($espaces[1]),
                'texte' => ltrim($sans),
            ];
        }

        if ($lignes === []) {
            throw new LancementEchoue('docker-compose.yml est vide ou illisible.');
        }

        $index = 0;
        $document = $this->lireNoeud($lignes, $index);

        if (isset($lignes[$index]) || ! is_array($document) || array_is_list($document)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        return $document;
    }

    /**
     * @param  list<array{indent: int, texte: string}>  $lignes
     * @return array<mixed>
     */
    private function lireNoeud(array $lignes, int &$index): array
    {
        if (! isset($lignes[$index])) {
            return [];
        }

        if (str_starts_with($lignes[$index]['texte'], '- ') || $lignes[$index]['texte'] === '-') {
            return $this->lireListe($lignes, $index, $lignes[$index]['indent']);
        }

        return $this->lireMap($lignes, $index, $lignes[$index]['indent']);
    }

    /**
     * @param  list<array{indent: int, texte: string}>  $lignes
     * @return array<mixed>
     */
    private function lireMap(array $lignes, int &$index, int $indent): array
    {
        $map = [];

        while (isset($lignes[$index]) && $lignes[$index]['indent'] === $indent && ! $this->estPuce($lignes[$index]['texte'])) {
            $couple = $this->decouperCle($lignes[$index]['texte']);

            if ($couple === null) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            [$cle, $reste] = $couple;
            $this->refuserBlocScalaire($reste);
            $index++;

            if ($reste === '') {
                if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indent) {
                    $map[$cle] = null;
                } else {
                    $map[$cle] = $this->lireNoeud($lignes, $index);
                }

                continue;
            }

            $map[$cle] = $this->scalaire($reste);
        }

        return $map;
    }

    /**
     * @param  list<array{indent: int, texte: string}>  $lignes
     * @return list<mixed>
     */
    private function lireListe(array $lignes, int &$index, int $indent): array
    {
        $liste = [];

        while (isset($lignes[$index]) && $lignes[$index]['indent'] === $indent && $this->estPuce($lignes[$index]['texte'])) {
            $texte = $lignes[$index]['texte'];
            $contenu = $texte === '-' ? '' : ltrim(substr($texte, 1));
            $index++;

            if ($contenu === '') {
                if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indent) {
                    $liste[] = null;
                } else {
                    $liste[] = $this->lireNoeud($lignes, $index);
                }

                continue;
            }

            $couple = $this->decouperCle($contenu);

            if ($couple === null) {
                $liste[] = $this->scalaire($contenu);

                continue;
            }

            [$cle, $reste] = $couple;
            $this->refuserBlocScalaire($reste);
            $map = [];

            if ($reste === '') {
                if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indent) {
                    $map[$cle] = null;
                } else {
                    $map[$cle] = $this->lireNoeud($lignes, $index);
                }
            } else {
                $map[$cle] = $this->scalaire($reste);
            }

            while (isset($lignes[$index]) && $lignes[$index]['indent'] > $indent && ! $this->estPuce($lignes[$index]['texte'])) {
                $suite = $this->decouperCle($lignes[$index]['texte']);

                if ($suite === null) {
                    throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
                }

                [$cleSuite, $resteSuite] = $suite;
                $indentCle = $lignes[$index]['indent'];
                $this->refuserBlocScalaire($resteSuite);
                $index++;

                if ($resteSuite === '') {
                    if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indentCle) {
                        $map[$cleSuite] = null;
                    } else {
                        $map[$cleSuite] = $this->lireNoeud($lignes, $index);
                    }
                } else {
                    $map[$cleSuite] = $this->scalaire($resteSuite);
                }
            }

            $liste[] = $map;
        }

        return $liste;
    }

    private function estPuce(string $texte): bool
    {
        return $texte === '-' || str_starts_with($texte, '- ');
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function decouperCle(string $texte): ?array
    {
        if (preg_match('/^([^:#][^:]*):(.*)$/', $texte, $morceaux) !== 1) {
            return null;
        }

        $reste = $morceaux[2];

        if ($reste !== '' && ! preg_match('/^\s/', $reste)) {
            return null;
        }

        return [$this->cle(rtrim($morceaux[1])), trim($reste)];
    }

    /**
     * Docker lit "privileged" comme privileged : on retire les guillemets pour voir la même clé que lui.
     */
    private function cle(string $cle): string
    {
        if (
            (strlen($cle) >= 2 && $cle[0] === '"' && str_ends_with($cle, '"'))
            || (strlen($cle) >= 2 && $cle[0] === "'" && str_ends_with($cle, "'"))
        ) {
            $this->refuserEchappement($cle);

            return substr($cle, 1, -1);
        }

        return $cle;
    }

    /**
     * Docker décoderait un échappement ("\x70rivileged"), pas nous : on refuse plutôt que de deviner.
     */
    private function refuserEchappement(string $texte): void
    {
        if ($texte !== '' && $texte[0] === '"' && str_contains($texte, '\\')) {
            throw new LancementEchoue('docker-compose.yml utilise un échappement YAML et ne peut pas être vérifié.');
        }
    }

    private function refuserBlocScalaire(string $reste): void
    {
        if ($reste === '|' || $reste === '>' || str_starts_with($reste, '|') || str_starts_with($reste, '>')) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }
    }

    private function scalaire(string $valeur): mixed
    {
        if ($valeur === '') {
            return '';
        }

        $premier = $valeur[0];

        if ($premier === '[' || $premier === '{') {
            return $this->lireFlux($valeur);
        }

        if (
            (strlen($valeur) >= 2 && $premier === '"' && str_ends_with($valeur, '"'))
            || (strlen($valeur) >= 2 && $premier === "'" && str_ends_with($valeur, "'"))
        ) {
            $this->refuserEchappement($valeur);

            return substr($valeur, 1, -1);
        }

        $bas = strtolower($valeur);

        if (in_array($bas, ['true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($bas, ['false', 'no', 'off'], true)) {
            return false;
        }

        if (in_array($bas, ['null', '~'], true)) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $valeur) === 1) {
            return (int) $valeur;
        }

        return $valeur;
    }

    private function lireFlux(string $texte): mixed
    {
        $texte = trim($texte);
        $ouvrant = $texte[0] ?? '';
        $fermant = $ouvrant === '[' ? ']' : '}';

        if ($ouvrant !== '[' && $ouvrant !== '{') {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        $fin = $this->finFlux($texte, 0);

        if ($fin !== strlen($texte) - 1 || $texte[$fin] !== $fermant) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        $morceaux = $this->separerFlux(substr($texte, 1, $fin - 1));

        if ($ouvrant === '[') {
            return array_map(fn (string $morceau): mixed => $this->scalaire(trim($morceau)), $morceaux);
        }

        $map = [];

        foreach ($morceaux as $morceau) {
            $morceau = trim($morceau);

            if (! str_contains($morceau, ':')) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            [$cle, $valeur] = explode(':', $morceau, 2);
            $cle = trim($cle);

            if (
                (str_starts_with($cle, '"') && str_ends_with($cle, '"'))
                || (str_starts_with($cle, "'") && str_ends_with($cle, "'"))
            ) {
                $cle = substr($cle, 1, -1);
            }

            $map[$cle] = $this->scalaire(trim($valeur));
        }

        return $map;
    }

    private function finFlux(string $texte, int $debut): int
    {
        $fermant = $texte[$debut] === '[' ? ']' : '}';
        $profondeur = 0;
        $guillemet = null;
        $longueur = strlen($texte);

        for ($i = $debut; $i < $longueur; $i++) {
            $caractere = $texte[$i];

            if ($guillemet !== null) {
                if ($caractere === '\\' && $guillemet === '"') {
                    $i++;

                    continue;
                }

                if ($caractere === $guillemet) {
                    $guillemet = null;
                }

                continue;
            }

            if ($caractere === '"' || $caractere === "'") {
                $guillemet = $caractere;

                continue;
            }

            if ($caractere === '[' || $caractere === '{') {
                $profondeur++;

                continue;
            }

            if (($caractere === ']' || $caractere === '}') && --$profondeur === 0 && $caractere === $fermant) {
                return $i;
            }
        }

        throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
    }

    /**
     * @return list<string>
     */
    private function separerFlux(string $corps): array
    {
        if (trim($corps) === '') {
            return [];
        }

        $morceaux = [];
        $debut = 0;
        $profondeur = 0;
        $guillemet = null;
        $longueur = strlen($corps);

        for ($i = 0; $i < $longueur; $i++) {
            $caractere = $corps[$i];

            if ($guillemet !== null) {
                if ($caractere === '\\' && $guillemet === '"') {
                    $i++;

                    continue;
                }

                if ($caractere === $guillemet) {
                    $guillemet = null;
                }

                continue;
            }

            if ($caractere === '"' || $caractere === "'") {
                $guillemet = $caractere;

                continue;
            }

            if ($caractere === '[' || $caractere === '{') {
                $profondeur++;

                continue;
            }

            if ($caractere === ']' || $caractere === '}') {
                $profondeur--;

                continue;
            }

            if ($caractere === ',' && $profondeur === 0) {
                $morceaux[] = substr($corps, $debut, $i - $debut);
                $debut = $i + 1;
            }
        }

        $morceaux[] = substr($corps, $debut);

        return $morceaux;
    }

    private function sansCommentaire(string $ligne): string
    {
        $guillemet = null;
        $longueur = strlen($ligne);

        for ($i = 0; $i < $longueur; $i++) {
            $caractere = $ligne[$i];

            if ($guillemet !== null) {
                if ($caractere === '\\' && $guillemet === '"') {
                    $i++;

                    continue;
                }

                if ($caractere === $guillemet) {
                    $guillemet = null;
                }

                continue;
            }

            if ($caractere === '"' || $caractere === "'") {
                $guillemet = $caractere;

                continue;
            }

            if ($caractere === '#' && ($i === 0 || $ligne[$i - 1] === ' ')) {
                return rtrim(substr($ligne, 0, $i));
            }
        }

        return $ligne;
    }
}
