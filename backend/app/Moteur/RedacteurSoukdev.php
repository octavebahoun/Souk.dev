<?php

declare(strict_types=1);

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Écrit un soukdev.json pour un dépôt qui n'en a pas : le service web et son port (lus dans le
 * docker-compose.yml, le Dockerfile ou la pile), et les variables (lues dans le docker-compose.yml
 * et le .env.example). Le dev relit la proposition avant de l'ajouter à son dépôt : chaque choix
 * est expliqué, et ce que la plateforme ne peut pas deviner lui est signalé.
 */
class RedacteurSoukdev
{
    /** Noms préférés pour le service exposé, dans l'ordre. */
    private const NOMS_WEB = ['web', 'app', 'front', 'frontend', 'nginx', 'caddy', 'site', 'www', 'server', 'serveur', 'api', 'backend'];

    /** Bases et caches reconnus dans les images des services. */
    private const BASES = [
        'postgres' => 'PostgreSQL',
        'mysql' => 'MySQL',
        'mariadb' => 'MariaDB',
        'mongo' => 'MongoDB',
        'redis' => 'Redis',
        'valkey' => 'Valkey',
    ];

    /**
     * Identifiants d'un service tiers (paiement, IA, e-mail…) : la plateforme ne peut pas les
     * inventer, chaque client donne les siens. Le reste de leurs réglages (modèle, adresse de
     * l'API) n'est pas un identifiant : il garde sa valeur d'exemple.
     */
    private const TIERS = '/(API_?KEY|ACCESS_KEY|CLIENT_ID|CLIENT_SECRET)/';

    private const FOURNISSEUR = '/^(STRIPE|PAYPAL|OPENAI|ANTHROPIC|DEEPSEEK|GROQ|MISTRAL|GEMINI|META|GOOGLE|GITHUB|FACEBOOK|TWITTER|AWS|S3|CLOUDINARY|SENDGRID|MAILGUN|RESEND|POSTMARK|TWILIO|SMTP|MAIL|PUSHER|FIREBASE|SUPABASE|SENTRY|VERCEL|NETLIFY|RENDER|HEROKU|CLOUDFLARE|SASPAY|FEDAPAY|KKIAPAY|CINETPAY|PAYDUNYA|MTN|MOOV|ORANGE|WAVE)_/';

    private const IDENTIFIANT = '/(KEY|SECRET|TOKEN|PASSWORD|PASS|USER|USERNAME|_ID|SID)$/';

    /**
     * Une clé publique, ou une paire de clés (VAPID, JWT asymétrique) : un hasard ne ferait pas
     * l'affaire, la clé vient d'un fournisseur ou d'une génération propre à l'appli.
     */
    private const CLE_FORMATEE = '/(PUBLIC|PUBLISHABLE|PRIVATE_KEY)/';

    /** Secrets propres à l'appli : la plateforme en génère un par copie. */
    private const SECRET = '/(PASSWORD|PASSWD|SECRET|TOKEN|SALT|PEPPER|JWT|_KEY|_KEYS)$|^(KEY|JWT_SECRET)$|_PASSWORD_/';

    /** Valeurs d'exemple qui ne sont pas de vraies valeurs. */
    private const EXEMPLE = '/^(changeme|change_me|change-me|xxx+|todo|secret|password|null|your[_-].*|<.*>|\.\.\.)$/i';

    public const MAX_VARIABLES = 50;

    public function __construct(private VerificateurCompose $verificateur) {}

    /**
     * @return array{manifeste: array<string, mixed>, deductions: list<string>, a_verifier: list<string>}
     */
    public function rediger(string $repertoire): array
    {
        $deductions = [];
        $aVerifier = [];
        $compose = $this->lire($repertoire, 'docker-compose.yml');
        $services = null;

        if ($compose === null) {
            $aVerifier[] = 'docker-compose.yml est absent : Souk.dev en a besoin pour lancer l\'appli. Ajoutez-le à la racine, avec le service web proposé ici.';
        } else {
            $services = $this->services($compose, $aVerifier);
            $this->verifierCompose($compose, $aVerifier);
        }

        [$nom, $port] = $this->serviceWeb($repertoire, $services, $deductions, $aVerifier);
        $this->signalerCloudDeLAuteur($repertoire, $aVerifier);
        $this->signalerBases($services, $deductions);
        $variables = $this->variables($repertoire, $compose, $services, $deductions, $aVerifier);

        $manifeste = [
            'version' => 1,
            'service_web' => ['nom' => $nom, 'port' => $port],
            'backend' => 'propre',
            'variables' => $variables,
        ];

        return ['manifeste' => $manifeste, 'deductions' => $deductions, 'a_verifier' => $aVerifier];
    }

    /** Le soukdev.json tel qu'il sera écrit dans le dépôt. */
    public static function texte(array $manifeste): string
    {
        return json_encode($manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param  list<string>  $aVerifier
     * @return array<string, array<string, mixed>>|null
     */
    private function services(string $compose, array &$aVerifier): ?array
    {
        try {
            // Les étiquettes propres à Compose (!reset, !override) ne doivent pas faire échouer la lecture.
            $document = Yaml::parse($compose, Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException) {
            $aVerifier[] = 'docker-compose.yml est illisible (YAML invalide) : le service web a été deviné sans lui.';

            return null;
        }

        if (! is_array($document) || ! isset($document['services']) || ! is_array($document['services']) || $document['services'] === []) {
            $aVerifier[] = 'docker-compose.yml ne déclare aucun service.';

            return null;
        }

        return array_filter($document['services'], fn ($service, $nom) => is_string($nom) && is_array($service), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Les mêmes règles qu'à la publication : le dev voit tout de suite ce que Souk.dev refusera.
     *
     * @param  list<string>  $aVerifier
     */
    private function verifierCompose(string $compose, array &$aVerifier): void
    {
        try {
            $this->verificateur->verifier($compose);
        } catch (LancementEchoue $exception) {
            $aVerifier[] = 'Souk.dev refusera ce docker-compose.yml en l\'état : '.str_replace("\n", ' ', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $services
     * @param  list<string>  $deductions
     * @param  list<string>  $aVerifier
     * @return array{string, int}
     */
    private function serviceWeb(string $repertoire, ?array $services, array &$deductions, array &$aVerifier): array
    {
        if ($services !== null && $services !== []) {
            $nom = $this->choisirService($services);
            $service = $services[$nom];
            $port = $this->portCompose($service);

            if ($port !== null) {
                $deductions[] = "Service web : « {$nom} », port {$port}, lus dans docker-compose.yml.";

                return [$nom, $port];
            }

            $port = $this->portDockerfile($repertoire, $service);
            if ($port !== null) {
                $deductions[] = "Service web : « {$nom} », port {$port}, lu dans son Dockerfile (EXPOSE).";

                return [$nom, $port];
            }

            $port = $this->portImage($service) ?? 80;
            $deductions[] = "Service web : « {$nom} » (docker-compose.yml), port {$port} supposé.";
            $aVerifier[] = "Le port {$port} du service « {$nom} » est une supposition : mettez celui sur lequel il écoute.";

            return [$nom, $port];
        }

        $port = $this->portDockerfile($repertoire, []);
        if ($port !== null) {
            $deductions[] = "Port {$port}, lu dans le Dockerfile (EXPOSE). Service nommé « web ».";

            return ['web', $port];
        }

        [$pile, $port] = $this->pile($repertoire);
        $deductions[] = $pile !== null
            ? "{$pile} détecté : port {$port} supposé. Service nommé « web »."
            : "Port {$port} supposé. Service nommé « web ».";
        $aVerifier[] = "Le port {$port} est une supposition : mettez celui sur lequel votre service web écoute.";

        return ['web', $port];
    }

    /** @param  array<string, array<string, mixed>>  $services */
    private function choisirService(array $services): string
    {
        $exposes = array_keys(array_filter($services, fn (array $service) => ! empty($service['ports']) || ! empty($service['expose'])));
        $candidats = $exposes !== [] ? $exposes : array_keys($services);

        foreach (self::NOMS_WEB as $prefere) {
            if (in_array($prefere, $candidats, true)) {
                return $prefere;
            }
        }

        return (string) $candidats[0];
    }

    /** @param  array<string, mixed>  $service */
    private function portCompose(array $service): ?int
    {
        foreach ((array) ($service['ports'] ?? []) as $entree) {
            $port = is_array($entree) ? ($entree['target'] ?? null) : $this->portCible((string) $entree);
            if ($this->portValide($port)) {
                return (int) $port;
            }
        }

        foreach ((array) ($service['expose'] ?? []) as $entree) {
            $port = explode('/', (string) $entree)[0];
            if ($this->portValide($port)) {
                return (int) $port;
            }
        }

        return null;
    }

    /** « 127.0.0.1:8080:80/tcp » → 80 : le port du conteneur, le dernier avant le protocole. */
    private function portCible(string $entree): ?string
    {
        $sansProtocole = explode('/', $entree)[0];
        $parties = explode(':', $sansProtocole);

        return end($parties) ?: null;
    }

    /** @param  array<string, mixed>  $service */
    private function portDockerfile(string $repertoire, array $service): ?int
    {
        $contexte = '.';
        $fichier = 'Dockerfile';
        $build = $service['build'] ?? null;

        if (is_string($build)) {
            $contexte = $build;
        } elseif (is_array($build)) {
            $contexte = is_string($build['context'] ?? null) ? $build['context'] : '.';
            $fichier = is_string($build['dockerfile'] ?? null) ? $build['dockerfile'] : 'Dockerfile';
        } elseif ($service !== []) {
            return null;
        }

        $texte = $this->lire($repertoire, trim($contexte, '/').'/'.$fichier);
        if ($texte === null || preg_match_all('/^\s*EXPOSE\s+(\d+)/mi', $texte, $prises) === 0) {
            return null;
        }

        // Plusieurs étapes : la dernière EXPOSE est celle de l'image finale.
        $port = end($prises[1]);

        return $this->portValide($port) ? (int) $port : null;
    }

    /** @param  array<string, mixed>  $service */
    private function portImage(array $service): ?int
    {
        $image = is_string($service['image'] ?? null) ? strtolower($service['image']) : '';

        return preg_match('/(^|\/)(nginx|httpd|caddy|apache)/', $image) === 1 ? 80 : null;
    }

    /** @return array{string|null, int} */
    private function pile(string $repertoire): array
    {
        $paquet = $this->lire($repertoire, 'package.json');
        if ($paquet !== null) {
            $dependances = json_decode($paquet, true);
            $noms = is_array($dependances) ? array_keys(array_merge((array) ($dependances['dependencies'] ?? []), (array) ($dependances['devDependencies'] ?? []))) : [];

            return match (true) {
                in_array('next', $noms, true) => ['Next.js', 3000],
                in_array('nuxt', $noms, true) => ['Nuxt', 3000],
                in_array('express', $noms, true) || in_array('fastify', $noms, true) => ['Node.js', 3000],
                in_array('vite', $noms, true) => ['Un site Vite (statique, servi par nginx)', 80],
                default => ['Node.js', 3000],
            };
        }

        $composer = $this->lire($repertoire, 'composer.json');
        if ($composer !== null) {
            return [str_contains($composer, 'laravel/framework') ? 'Laravel' : 'PHP', 80];
        }

        if ($this->lire($repertoire, 'manage.py') !== null) {
            return ['Django', 8000];
        }

        if ($this->lire($repertoire, 'requirements.txt') !== null || $this->lire($repertoire, 'pyproject.toml') !== null) {
            return ['Python', 8000];
        }

        if ($this->lire($repertoire, 'go.mod') !== null) {
            return ['Go', 8080];
        }

        if ($this->lire($repertoire, 'index.html') !== null) {
            return ['Un site statique (servi par nginx)', 80];
        }

        return [null, 80];
    }

    /**
     * Une appli bâtie sur le Supabase ou le Firebase de son auteur ne tourne pas seule : chaque
     * client devrait ouvrir son propre compte. Pas encore pris en charge (issue #30).
     *
     * @param  list<string>  $aVerifier
     */
    private function signalerCloudDeLAuteur(string $repertoire, array &$aVerifier): void
    {
        $paquet = json_decode((string) $this->lire($repertoire, 'package.json'), true);
        $dependances = is_array($paquet) ? array_keys([...(array) ($paquet['dependencies'] ?? []), ...(array) ($paquet['devDependencies'] ?? [])]) : [];
        $utilise = fn (string $prefixe) => array_filter($dependances, fn ($nom) => is_string($nom) && str_starts_with($nom, $prefixe)) !== [];
        $nuages = array_filter([
            'Supabase' => $utilise('@supabase/') || $this->lire($repertoire, 'supabase/config.toml') !== null,
            'Firebase' => $utilise('firebase'),
        ]);

        foreach (array_keys($nuages) as $nuage) {
            $aVerifier[] = "Cette appli dépend d'un compte {$nuage} : chaque client devrait ouvrir le sien. Souk.dev ne fournit pas encore de {$nuage} par copie (prévu après le hackathon).";
        }
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $services
     * @param  list<string>  $deductions
     */
    private function signalerBases(?array $services, array &$deductions): void
    {
        foreach ($services ?? [] as $nom => $service) {
            $image = is_string($service['image'] ?? null) ? strtolower($service['image']) : '';

            foreach (self::BASES as $motif => $libelle) {
                if (preg_match('/(^|\/)'.$motif.'/', $image) === 1) {
                    $deductions[] = "{$libelle} dans docker-compose.yml (service « {$nom} ») : l'appli apporte sa propre base (backend « propre »).";

                    continue 2;
                }
            }
        }
    }

    /**
     * Les variables que la copie doit recevoir : celles que docker-compose.yml lit (${NOM}), et
     * celles du .env.example quand le compose lit un .env, ou quand il n'y a pas de compose.
     *
     * @param  array<string, array<string, mixed>>|null  $services
     * @param  list<string>  $deductions
     * @param  list<string>  $aVerifier
     * @return list<array<string, mixed>>
     */
    private function variables(string $repertoire, ?string $compose, ?array $services, array &$deductions, array &$aVerifier): array
    {
        $exemple = $this->envExemple($repertoire);
        $references = $compose !== null ? $this->references($compose) : [];
        $litUnEnv = $compose === null || $this->litUnEnv($services);
        $noms = array_keys($references);

        if ($litUnEnv) {
            $noms = array_values(array_unique([...$noms, ...array_keys($exemple)]));
        }

        $variables = [];
        $parSource = ['client' => [], 'plateforme' => [], 'fixe' => []];

        foreach ($noms as $nom) {
            if (preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $nom) !== 1 || str_starts_with($nom, 'SOUKDEV_')) {
                continue;
            }

            if ($nom === 'APP_KEY') {
                $aVerifier[] = 'APP_KEY n\'est pas déclarée : Laravel attend une clé au format base64:…, que la plateforme ne génère pas. Créez-la au démarrage du conteneur (php artisan key:generate --force).';

                continue;
            }

            // La valeur du .env.example, sinon le défaut du compose (${NOM:-défaut}).
            $valeur = ($exemple[$nom]['valeur'] ?? '') !== '' ? $exemple[$nom]['valeur'] : ($references[$nom] ?? '');
            $commentaire = $exemple[$nom]['commentaire'] ?? null;
            $variable = $this->classer($nom, $valeur, $commentaire);
            $variables[] = $variable;
            $parSource[$variable['source']][] = $nom;

            if ($variable['source'] === 'fixe' && preg_match('/localhost|127\.0\.0\.1/', (string) $variable['valeur']) === 1) {
                $aVerifier[] = "{$nom} vaut « {$variable['valeur']} » : en ligne, la copie n'est pas sur localhost. Corrigez la valeur, ou demandez-la au client.";
            }
        }

        if (count($variables) > self::MAX_VARIABLES) {
            $aVerifier[] = 'Plus de '.self::MAX_VARIABLES.' variables : seules les '.self::MAX_VARIABLES.' premières sont gardées.';
            $variables = array_slice($variables, 0, self::MAX_VARIABLES);
        }

        if ($parSource['plateforme'] !== []) {
            $deductions[] = 'Générées par la plateforme pour chaque copie : '.implode(', ', $parSource['plateforme']).'.';
            $aVerifier[] = 'Les secrets générés font 48 caractères hexadécimaux : vérifiez que votre appli accepte ce format.';
        }
        if ($parSource['client'] !== []) {
            $deductions[] = 'Demandées au client à la commande : '.implode(', ', $parSource['client']).'.';
        }
        if ($parSource['fixe'] !== []) {
            $deductions[] = 'Valeurs fixes, reprises du .env.example ou du docker-compose.yml : '.implode(', ', $parSource['fixe']).'.';
        }
        if ($variables === []) {
            $deductions[] = 'Aucune variable à fournir : ni ${NOM} dans docker-compose.yml, ni .env.example.';
        }

        return $variables;
    }

    /** @return array<string, mixed> */
    private function classer(string $nom, string $valeur, ?string $commentaire): array
    {
        if (preg_match(self::TIERS, $nom) === 1 || (preg_match(self::FOURNISSEUR, $nom) === 1 && preg_match(self::IDENTIFIANT, $nom) === 1)) {
            return ['nom' => $nom, 'source' => 'client', 'libelle' => $this->libelle($nom, $commentaire, 'votre propre compte'), 'requis' => false];
        }

        if (preg_match(self::SECRET, $nom) === 1 && preg_match(self::CLE_FORMATEE, $nom) !== 1) {
            return ['nom' => $nom, 'source' => 'plateforme'];
        }

        if ($valeur !== '' && preg_match(self::EXEMPLE, $valeur) !== 1) {
            return ['nom' => $nom, 'source' => 'fixe', 'valeur' => mb_substr($valeur, 0, 500)];
        }

        return ['nom' => $nom, 'source' => 'client', 'libelle' => $this->libelle($nom, $commentaire), 'requis' => true];
    }

    private function libelle(string $nom, ?string $commentaire, ?string $precision = null): string
    {
        $texte = $commentaire !== null && $commentaire !== ''
            ? $commentaire
            : ucfirst(strtolower(str_replace('_', ' ', $nom))).($precision !== null ? " ({$precision})" : '');

        return mb_substr($texte, 0, 100);
    }

    /**
     * ${NOM}, ${NOM:-défaut}, ${NOM:?message}, $NOM ; $$ échappe un dollar.
     *
     * @return array<string, string> nom => valeur par défaut (vide sans défaut)
     */
    private function references(string $compose): array
    {
        $references = [];
        $sansEchappes = str_replace('$$', '', $compose);

        preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)(?:(:?-)([^}]*)|:?[?+][^}]*)?\}|\$([A-Za-z_][A-Za-z0-9_]*)/', $sansEchappes, $prises, PREG_SET_ORDER);

        foreach ($prises as $prise) {
            $nom = $prise[1] !== '' ? $prise[1] : ($prise[4] ?? '');
            if ($nom === '' || array_key_exists($nom, $references)) {
                continue;
            }

            $references[$nom] = isset($prise[2]) && $prise[2] !== '' ? trim($prise[3] ?? '', '"\'') : '';
        }

        return $references;
    }

    /** @param  array<string, array<string, mixed>>|null  $services */
    private function litUnEnv(?array $services): bool
    {
        foreach ($services ?? [] as $service) {
            foreach ((array) ($service['env_file'] ?? []) as $fichier) {
                $chemin = is_array($fichier) ? ($fichier['path'] ?? '') : $fichier;
                if (is_string($chemin) && basename($chemin) === '.env') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Le premier modèle d'environnement trouvé : ses valeurs, et le commentaire de chaque ligne
     * (en fin de ligne ou juste au-dessus), qui devient le libellé montré au client.
     *
     * @return array<string, array{valeur: string, commentaire: string|null}>
     */
    private function envExemple(string $repertoire): array
    {
        foreach (['.env.example', '.env.sample', '.env.dist', '.env.template'] as $nomFichier) {
            $texte = $this->lire($repertoire, $nomFichier);
            if ($texte === null) {
                continue;
            }

            $variables = [];
            $commentaire = null;

            foreach (preg_split('/\R/', $texte) ?: [] as $ligne) {
                $ligne = trim($ligne);

                if ($ligne === '') {
                    $commentaire = null;

                    continue;
                }

                if (str_starts_with($ligne, '#')) {
                    $commentaire = trim(ltrim($ligne, '# '));

                    continue;
                }

                if (preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $ligne, $prise) !== 1) {
                    continue;
                }

                [$valeur, $enFin] = $this->valeurEtCommentaire($prise[2]);
                $variables[$prise[1]] = ['valeur' => $valeur, 'commentaire' => $enFin ?? $commentaire];
                $commentaire = null;
            }

            return $variables;
        }

        return [];
    }

    /** @return array{string, string|null} */
    private function valeurEtCommentaire(string $brut): array
    {
        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"\s*(?:#\s*(.*))?$/', $brut, $prise) === 1
            || preg_match("/^'([^']*)'\s*(?:#\s*(.*))?$/", $brut, $prise) === 1) {
            return [$prise[1], isset($prise[2]) && $prise[2] !== '' ? trim($prise[2]) : null];
        }

        // « CLE=   # commentaire » : pas de valeur, seulement le commentaire.
        $morceaux = preg_split('/(?:^|\s+)#\s*/', $brut, 2) ?: [$brut];

        return [trim($morceaux[0]), isset($morceaux[1]) && trim($morceaux[1]) !== '' ? trim($morceaux[1]) : null];
    }

    /** Un fichier du dépôt, jamais en dehors (pas de lien, pas de « .. »), 512 Ko au plus. */
    private function lire(string $repertoire, string $chemin): ?string
    {
        // « ./api/Dockerfile » → « api/Dockerfile » ; le point d'un « .env.example » reste.
        $chemin = ltrim((string) preg_replace('#^(\./)+#', '', $chemin), '/');
        if ($chemin === '' || str_contains($chemin, '..')) {
            return null;
        }

        $complet = rtrim($repertoire, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$chemin;
        if (! is_file($complet) || is_link($complet) || filesize($complet) > 512 * 1024) {
            return null;
        }

        $texte = file_get_contents($complet);

        return is_string($texte) ? $texte : null;
    }

    private function portValide(mixed $port): bool
    {
        return is_numeric($port) && (int) $port >= 1 && (int) $port <= 65535 && (string) (int) $port === (string) $port;
    }
}
