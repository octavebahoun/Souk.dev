<?php

declare(strict_types=1);

namespace App\Moteur;

/**
 * Écrit les fichiers Docker d'un dépôt qui n'en a pas, pour les piles sûres seulement : site
 * statique, site Vite ou Create React App, Next.js, serveur Node (Express, Fastify, Koa, Hono).
 * Ailleurs, rien : mieux vaut dire au dev ce qui manque que lui proposer un fichier deviné.
 * Les fichiers ne sont pas lancés ici ; la mesure de la mémoire, après la publication, les lance.
 *
 * Voir l'issue #33 : la suite (Souk AI pour les autres piles, test de lancement avant de proposer).
 */
class EcrivainDocker
{
    private const IMAGE_NGINX = 'nginx:1.27-alpine';

    /** Serveurs Node reconnus (dépendance => nom affiché). */
    private const SERVEURS_NODE = ['express' => 'Express', 'fastify' => 'Fastify', 'koa' => 'Koa', 'hono' => 'Hono'];

    /** Variables lues au moment du build, selon la pile : elles passent en arguments de construction. */
    private const PREFIXES_BUILD = ['vite' => 'VITE_', 'cra' => 'REACT_APP_', 'next' => 'NEXT_PUBLIC_'];

    /**
     * @param  list<string>  $variables  noms des variables du soukdev.json
     * @return array{pile: string, port: int, fichiers: array<string, string>, notes: list<string>}|null
     */
    public function ecrire(string $repertoire, array $variables): ?array
    {
        $paquet = $this->paquet($repertoire);

        if ($paquet === null) {
            return $this->lire($repertoire, 'index.html') !== null ? $this->statique() : null;
        }

        $dependances = $this->dependances($paquet);
        $scripts = is_array($paquet['scripts'] ?? null) ? $paquet['scripts'] : [];
        $gestionnaire = $this->gestionnaire($repertoire);

        if ($gestionnaire === null) {
            return null;
        }

        $node = $this->versionNode($repertoire, $paquet);

        if (isset($dependances['next']) && isset($scripts['build'])) {
            return $this->next($gestionnaire, $node, $variables, $this->variablesBuild('next', $variables));
        }

        if (isset($dependances['vite']) && isset($scripts['build'])) {
            return $this->spa('Site Vite', $gestionnaire, $node, $this->dossierVite($repertoire), $this->variablesBuild('vite', $variables));
        }

        if (isset($dependances['react-scripts']) && isset($scripts['build'])) {
            return $this->spa('Site Create React App', $gestionnaire, $node, 'build', $this->variablesBuild('cra', $variables));
        }

        foreach (self::SERVEURS_NODE as $dependance => $nom) {
            if (isset($dependances[$dependance]) && isset($scripts['start'])) {
                return $this->serveurNode($nom, $repertoire, $gestionnaire, $node, isset($scripts['build']), $variables);
            }
        }

        return null;
    }

    /**
     * Un docker-compose.yml pour un dépôt qui a déjà son Dockerfile : il le construit, expose le
     * port et passe les variables au conteneur.
     *
     * @param  list<string>  $variables
     * @param  list<string>  $argumentsBuild  variables passées à la construction plutôt qu'au conteneur
     */
    public static function compose(int $port, array $variables, array $argumentsBuild = [], string $service = 'web'): string
    {
        $lignes = ['services:', "  {$service}:"];

        if ($argumentsBuild === []) {
            $lignes[] = '    build: .';
        } else {
            $lignes[] = '    build:';
            $lignes[] = '      context: .';
            $lignes[] = '      args:';
            foreach ($argumentsBuild as $nom) {
                $lignes[] = "        {$nom}: \${{$nom}}";
            }
        }

        $lignes[] = '    restart: unless-stopped';
        $lignes[] = '    expose:';
        $lignes[] = "      - \"{$port}\"";

        $auConteneur = array_values(array_diff($variables, $argumentsBuild));
        if ($auConteneur !== []) {
            $lignes[] = '    environment:';
            foreach ($auConteneur as $nom) {
                $lignes[] = "      {$nom}: \${{$nom}}";
            }
        }

        return implode("\n", $lignes)."\n";
    }

    /** @return array{pile: string, port: int, fichiers: array<string, string>, notes: list<string>} */
    private function statique(): array
    {
        return [
            'pile' => 'Site statique',
            'port' => 80,
            'fichiers' => [
                'Dockerfile' => implode("\n", [
                    '# Site statique servi par nginx (écrit par Souk.dev).',
                    'FROM '.self::IMAGE_NGINX,
                    'COPY . /usr/share/nginx/html',
                    '',
                ]),
                '.dockerignore' => $this->dockerignore(),
                'docker-compose.yml' => self::compose(80, []),
            ],
            'notes' => [],
        ];
    }

    /**
     * @param  array{nom: string, installer: string, lancer: string, corepack: bool}  $gestionnaire
     * @param  list<string>  $argumentsBuild
     * @return array{pile: string, port: int, fichiers: array<string, string>, notes: list<string>}
     */
    private function spa(string $pile, array $gestionnaire, int $node, string $sortie, array $argumentsBuild): array
    {
        $dockerfile = [
            "# {$pile} : construit avec Node {$node} ({$gestionnaire['nom']}), puis servi par nginx (écrit par Souk.dev).",
            "FROM node:{$node}-slim AS construction",
            'WORKDIR /app',
            ...$this->arguments($argumentsBuild),
            ...($gestionnaire['corepack'] ? ['RUN corepack enable'] : []),
            'COPY . .',
            "RUN {$gestionnaire['installer']}",
            "RUN {$gestionnaire['lancer']} build",
            '',
            'FROM '.self::IMAGE_NGINX,
            'COPY nginx.conf /etc/nginx/conf.d/default.conf',
            "COPY --from=construction /app/{$sortie} /usr/share/nginx/html",
            '',
        ];

        return [
            'pile' => "{$pile} ({$gestionnaire['nom']}, Node {$node}, dossier « {$sortie} »)",
            'port' => 80,
            'fichiers' => [
                'Dockerfile' => implode("\n", $dockerfile),
                'nginx.conf' => implode("\n", [
                    '# Les routes de l\'appli (/projets/…) renvoient index.html : le routeur du navigateur prend le relais.',
                    'server {',
                    '    listen 80;',
                    '    server_name _;',
                    '    root /usr/share/nginx/html;',
                    '    index index.html;',
                    '',
                    '    location / {',
                    '        try_files $uri $uri/ /index.html;',
                    '    }',
                    '}',
                    '',
                ]),
                '.dockerignore' => $this->dockerignore(),
                'docker-compose.yml' => self::compose(80, $argumentsBuild, $argumentsBuild),
            ],
            'notes' => $argumentsBuild !== []
                ? ['Les variables '.implode(', ', $argumentsBuild).' sont figées à la construction : chaque copie est construite avec les siennes.']
                : [],
        ];
    }

    /**
     * @param  array{nom: string, installer: string, lancer: string, corepack: bool}  $gestionnaire
     * @param  list<string>  $variables
     * @param  list<string>  $argumentsBuild
     * @return array{pile: string, port: int, fichiers: array<string, string>, notes: list<string>}
     */
    private function next(array $gestionnaire, int $node, array $variables, array $argumentsBuild): array
    {
        $dockerfile = [
            "# Next.js, construit puis lancé avec Node {$node} ({$gestionnaire['nom']}) (écrit par Souk.dev).",
            "FROM node:{$node}-slim",
            'WORKDIR /app',
            ...$this->arguments($argumentsBuild),
            ...($gestionnaire['corepack'] ? ['RUN corepack enable'] : []),
            'COPY . .',
            "RUN {$gestionnaire['installer']}",
            "RUN {$gestionnaire['lancer']} build",
            'ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0',
            'EXPOSE 3000',
            'CMD '.$this->commande($gestionnaire, 'start'),
            '',
        ];

        return [
            'pile' => "Next.js ({$gestionnaire['nom']}, Node {$node})",
            'port' => 3000,
            'fichiers' => [
                'Dockerfile' => implode("\n", $dockerfile),
                '.dockerignore' => $this->dockerignore(),
                'docker-compose.yml' => self::compose(3000, $variables, $argumentsBuild),
            ],
            'notes' => $argumentsBuild !== []
                ? ['Les variables '.implode(', ', $argumentsBuild).' sont figées à la construction : chaque copie est construite avec les siennes.']
                : [],
        ];
    }

    /**
     * @param  array{nom: string, installer: string, lancer: string, corepack: bool}  $gestionnaire
     * @param  list<string>  $variables
     * @return array{pile: string, port: int, fichiers: array<string, string>, notes: list<string>}
     */
    private function serveurNode(string $nom, string $repertoire, array $gestionnaire, int $node, bool $construire, array $variables): array
    {
        [$port, $source] = $this->portNode($repertoire);

        $dockerfile = [
            "# Serveur {$nom}, lancé avec Node {$node} ({$gestionnaire['nom']}) (écrit par Souk.dev).",
            "FROM node:{$node}-slim",
            'WORKDIR /app',
            ...($gestionnaire['corepack'] ? ['RUN corepack enable'] : []),
            'COPY . .',
            "RUN {$gestionnaire['installer']}",
            ...($construire ? ["RUN {$gestionnaire['lancer']} build"] : []),
            "ENV NODE_ENV=production PORT={$port}",
            "EXPOSE {$port}",
            'CMD '.$this->commande($gestionnaire, 'start'),
            '',
        ];

        return [
            'pile' => "Serveur {$nom} ({$gestionnaire['nom']}, Node {$node})",
            'port' => $port,
            'fichiers' => [
                'Dockerfile' => implode("\n", $dockerfile),
                '.dockerignore' => $this->dockerignore(),
                'docker-compose.yml' => self::compose($port, $variables),
            ],
            'notes' => $source === null
                ? ["Le port {$port} est supposé : le serveur doit écouter sur la variable PORT, ou sur ce port."]
                : ["Port {$port}, lu dans {$source}."],
        ];
    }

    /** @return array{int, string|null} */
    private function portNode(string $repertoire): array
    {
        $exemple = (string) $this->lire($repertoire, '.env.example');
        if (preg_match('/^\s*PORT\s*=\s*"?(\d{2,5})"?/m', $exemple, $prise) === 1 && $this->portValide($prise[1])) {
            return [(int) $prise[1], '.env.example'];
        }

        foreach (['server.js', 'index.js', 'app.js', 'main.js', 'src/server.js', 'src/index.js', 'src/app.js', 'src/server.ts', 'src/index.ts', 'src/main.ts'] as $fichier) {
            $code = $this->lire($repertoire, $fichier);
            if ($code !== null && preg_match('/(?:PORT\s*(?:\|\||\?\?)\s*|\.listen\(\s*)(\d{2,5})/', $code, $prise) === 1 && $this->portValide($prise[1])) {
                return [(int) $prise[1], $fichier];
            }
        }

        return [3000, null];
    }

    /** @return array<string, mixed>|null */
    private function paquet(string $repertoire): ?array
    {
        $texte = $this->lire($repertoire, 'package.json');
        if ($texte === null) {
            return null;
        }

        $paquet = json_decode($texte, true);

        return is_array($paquet) ? $paquet : [];
    }

    /** @return array<string, mixed> */
    private function dependances(array $paquet): array
    {
        return [...(array) ($paquet['dependencies'] ?? []), ...(array) ($paquet['devDependencies'] ?? [])];
    }

    /**
     * Le gestionnaire du fichier de verrouillage ; null pour Bun et ce qu'on ne connaît pas.
     *
     * @return array{nom: string, installer: string, lancer: string, corepack: bool}|null
     */
    private function gestionnaire(string $repertoire): ?array
    {
        return match (true) {
            $this->lire($repertoire, 'pnpm-lock.yaml') !== null => ['nom' => 'pnpm', 'installer' => 'pnpm install --frozen-lockfile', 'lancer' => 'pnpm run', 'corepack' => true],
            $this->lire($repertoire, 'yarn.lock') !== null => ['nom' => 'Yarn', 'installer' => 'yarn install', 'lancer' => 'yarn run', 'corepack' => true],
            $this->lire($repertoire, 'bun.lockb') !== null || $this->lire($repertoire, 'bun.lock') !== null => null,
            $this->lire($repertoire, 'package-lock.json') !== null => ['nom' => 'npm', 'installer' => 'npm ci', 'lancer' => 'npm run', 'corepack' => false],
            default => ['nom' => 'npm', 'installer' => 'npm install', 'lancer' => 'npm run', 'corepack' => false],
        };
    }

    /** @param  array{nom: string, lancer: string}  $gestionnaire */
    private function commande(array $gestionnaire, string $script): string
    {
        return json_encode([...explode(' ', $gestionnaire['lancer']), $script], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** La version de Node demandée par le dépôt (.nvmrc, engines), entre 18 et 24 ; 22 sinon. */
    private function versionNode(string $repertoire, array $paquet): int
    {
        foreach ([(string) $this->lire($repertoire, '.nvmrc'), (string) $this->lire($repertoire, '.node-version'), (string) ($paquet['engines']['node'] ?? '')] as $texte) {
            if (preg_match('/(\d{2})/', $texte, $prise) === 1 && (int) $prise[1] >= 18 && (int) $prise[1] <= 24) {
                return (int) $prise[1];
            }
        }

        return 22;
    }

    private function dossierVite(string $repertoire): string
    {
        foreach (['vite.config.ts', 'vite.config.js', 'vite.config.mts', 'vite.config.mjs'] as $fichier) {
            $config = $this->lire($repertoire, $fichier);
            if ($config !== null && preg_match('/outDir\s*:\s*[\'"]([A-Za-z0-9._\/-]+)[\'"]/', $config, $prise) === 1 && ! str_contains($prise[1], '..')) {
                return trim($prise[1], './') ?: 'dist';
            }
        }

        return 'dist';
    }

    /**
     * @param  list<string>  $variables
     * @return list<string>
     */
    private function variablesBuild(string $pile, array $variables): array
    {
        return array_values(array_filter($variables, fn (string $nom) => str_starts_with($nom, self::PREFIXES_BUILD[$pile])));
    }

    /**
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function arguments(array $arguments): array
    {
        return array_map(fn (string $nom) => "ARG {$nom}", $arguments);
    }

    /** Ni l'historique, ni les dépendances locales, ni les vrais .env n'entrent dans l'image. */
    private function dockerignore(): string
    {
        return implode("\n", ['.git', 'node_modules', '.env', '.env.*', '!.env.example', 'Dockerfile', 'docker-compose.yml', '']);
    }

    /** Un fichier du dépôt, jamais un lien, 512 Ko au plus. */
    private function lire(string $repertoire, string $chemin): ?string
    {
        $complet = rtrim($repertoire, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$chemin;
        if (str_contains($chemin, '..') || ! is_file($complet) || is_link($complet) || filesize($complet) > 512 * 1024) {
            return null;
        }

        $texte = file_get_contents($complet);

        return is_string($texte) ? $texte : null;
    }

    private function portValide(string $port): bool
    {
        return (int) $port >= 1 && (int) $port <= 65535;
    }
}
