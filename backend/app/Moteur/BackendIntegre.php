<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Base neuve pour une copie « integre » : Postgres, PostgREST, migrations .sql,
 * puis SOUKDEV_URL et SOUKDEV_CLE dans l'environnement de l'appli.
 */
class BackendIntegre
{
    public const IMAGE_POSTGRES = 'postgres:16-alpine';

    public const IMAGE_POSTGREST = 'postgrest/postgrest:v12.2.3';

    public function __construct(
        private FichiersMigration $fichiers,
        private LanceurCompose $lanceur,
    ) {}

    /**
     * @param  array<string, mixed>  $manifeste
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    public function completerEnv(string $repertoire, array $manifeste, array $env): array
    {
        if (($manifeste['backend'] ?? '') !== 'integre') {
            return $env;
        }

        $this->fichiers->lister($repertoire, (string) ($manifeste['migrations'] ?? ''));
        $services = $this->servicesDev($repertoire);

        foreach (['SOUKDEV_URL', 'SOUKDEV_CLE'] as $nom) {
            if (array_key_exists($nom, $env)) {
                throw new LancementEchoue("La variable {$nom} est réservée au backend intégré.");
            }
        }

        $motDePasse = bin2hex(random_bytes(24));
        $secret = bin2hex(random_bytes(24));
        $cle = $this->cle($secret, 'soukdev_anon');
        $this->ecrireCompose($repertoire, $motDePasse, $secret, $cle, $services);

        $env['SOUKDEV_URL'] = 'http://soukdev-api:3000';
        $env['SOUKDEV_CLE'] = $cle;

        return $env;
    }

    public function appliquer(CopieLocale $copie): void
    {
        if (($copie->manifeste['backend'] ?? '') !== 'integre') {
            return;
        }

        $this->attendre($copie);
        $this->executer($copie, $this->roleAnon(), 'role');

        foreach ($this->fichiers->lister($copie->repertoire, (string) $copie->manifeste['migrations']) as $fichier) {
            $contenu = file_get_contents($fichier);

            if ($contenu === false) {
                throw new LancementEchoue(basename($fichier).' est illisible.');
            }

            $this->executer($copie, $contenu, basename($fichier));
        }

        $this->executer($copie, $this->droitsAnon(), 'droits');
        $this->rechargerApi($copie);
    }

    /**
     * @return list<string>
     */
    private function servicesDev(string $repertoire): array
    {
        $compose = $repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml';
        $contenu = is_file($compose) ? file_get_contents($compose) : false;

        if ($contenu === false) {
            throw new LancementEchoue('docker-compose.yml est absent à la racine du dépôt.');
        }

        $noms = $this->lanceur->nomsServices($contenu);

        foreach (['soukdev-db', 'soukdev-api'] as $reserve) {
            if (in_array($reserve, $noms, true)) {
                throw new LancementEchoue("Le service « {$reserve} » est réservé au backend intégré.");
            }
        }

        return $noms;
    }

    /**
     * @param  list<string>  $services
     */
    private function ecrireCompose(string $repertoire, string $motDePasse, string $secret, string $cle, array $services): void
    {
        $postgres = $this->image(self::IMAGE_POSTGRES, 'moteur.backend_image_postgres');
        $postgrest = $this->image(self::IMAGE_POSTGREST, 'moteur.backend_image_postgrest');
        $uri = 'postgres://soukdev:'.$motDePasse.'@soukdev-db:5432/soukdev';

        $yaml = <<<YAML
services:
  soukdev-db:
    image: {$postgres}
    environment:
      POSTGRES_USER: soukdev
      POSTGRES_PASSWORD: "{$motDePasse}"
      POSTGRES_DB: soukdev
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U soukdev -d soukdev"]
      interval: 2s
      timeout: 5s
      retries: 30
  soukdev-api:
    image: {$postgrest}
    environment:
      PGRST_DB_URI: "{$uri}"
      PGRST_DB_SCHEMAS: public
      PGRST_DB_ANON_ROLE: soukdev_anon
      PGRST_JWT_SECRET: "{$secret}"
      PGRST_SERVER_PORT: "3000"
    depends_on:
      soukdev-db:
        condition: service_healthy
    restart: unless-stopped

YAML;

        foreach ($services as $service) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$/', $service) !== 1) {
                throw new LancementEchoue('Un nom de service empêche d\'injecter le backend intégré.');
            }

            $yaml .= "  {$service}:\n";
            $yaml .= "    environment:\n";
            $yaml .= "      SOUKDEV_URL: \"http://soukdev-api:3000\"\n";
            $yaml .= "      SOUKDEV_CLE: \"{$cle}\"\n";
        }

        $chemin = $repertoire.DIRECTORY_SEPARATOR.'docker-compose.backend.yml';

        if (file_put_contents($chemin, $yaml) === false) {
            throw new LancementEchoue('Impossible d\'écrire le backend intégré.');
        }
    }

    private function attendre(CopieLocale $copie): void
    {
        $tentatives = $this->tentatives();

        for ($essai = 1; $essai <= $tentatives; $essai++) {
            $resultat = Process::path($copie->repertoire)
                ->timeout(15)
                ->run([
                    ...$this->lanceur->argumentsCompose($copie->repertoire, $copie->id),
                    'exec', '-T', 'soukdev-db',
                    'pg_isready', '-U', 'soukdev', '-d', 'soukdev',
                ]);

            if ($resultat->successful()) {
                return;
            }

            if ($essai < $tentatives) {
                sleep(1);
            }
        }

        throw new LancementEchoue('La base du backend intégré ne répond pas.');
    }

    private function executer(CopieLocale $copie, string $sql, string $nom): void
    {
        $resultat = Process::path($copie->repertoire)
            ->input($sql)
            ->timeout(60)
            ->run([
                ...$this->lanceur->argumentsCompose($copie->repertoire, $copie->id),
                'exec', '-T', 'soukdev-db',
                'psql', '-U', 'soukdev', '-d', 'soukdev', '-v', 'ON_ERROR_STOP=1', '-f', '-',
            ]);

        if ($resultat->failed()) {
            throw new LancementEchoue("La migration {$nom} a échoué.");
        }
    }

    private function rechargerApi(CopieLocale $copie): void
    {
        $resultat = Process::path($copie->repertoire)
            ->timeout(60)
            ->run([
                ...$this->lanceur->argumentsCompose($copie->repertoire, $copie->id),
                'restart', 'soukdev-api',
            ]);

        if ($resultat->failed()) {
            throw new LancementEchoue('L\'API du backend intégré n\'a pas redémarré.');
        }
    }

    private function roleAnon(): string
    {
        return <<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'soukdev_anon') THEN
        CREATE ROLE soukdev_anon NOLOGIN;
    END IF;
END
$$;
GRANT USAGE ON SCHEMA public TO soukdev_anon;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO soukdev_anon;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO soukdev_anon;
SQL;
    }

    private function droitsAnon(): string
    {
        return <<<'SQL'
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO soukdev_anon;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO soukdev_anon;
SQL;
    }

    private function cle(string $secret, string $role): string
    {
        $entete = $this->base64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $charge = $this->base64Url((string) json_encode(['role' => $role], JSON_THROW_ON_ERROR));
        $signature = $this->base64Url(hash_hmac('sha256', $entete.'.'.$charge, $secret, true));

        return $entete.'.'.$charge.'.'.$signature;
    }

    private function base64Url(string $valeur): string
    {
        return rtrim(strtr(base64_encode($valeur), '+/', '-_'), '=');
    }

    private function image(string $defaut, string $cleConfig): string
    {
        if (! function_exists('config')) {
            return $defaut;
        }

        try {
            $image = config($cleConfig);
        } catch (Throwable) {
            return $defaut;
        }

        return is_string($image) && $image !== '' ? $image : $defaut;
    }

    private function tentatives(): int
    {
        if (! function_exists('config')) {
            return 30;
        }

        try {
            return max(1, (int) config('moteur.backend_tentatives'));
        } catch (Throwable) {
            return 30;
        }
    }
}
