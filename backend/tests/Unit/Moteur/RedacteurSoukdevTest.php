<?php

namespace Tests\Unit\Moteur;

use App\Moteur\EcrivainDocker;
use App\Moteur\RedacteurSoukdev;
use App\Moteur\ValidateurSoukdev;
use App\Moteur\VerificateurCompose;
use PHPUnit\Framework\TestCase;

class RedacteurSoukdevTest extends TestCase
{
    private string $depot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depot = sys_get_temp_dir().'/soukdev-redaction-'.bin2hex(random_bytes(4));
        mkdir($this->depot);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->depot));

        parent::tearDown();
    }

    /** @param  array<string, string>  $fichiers */
    private function rediger(array $fichiers): array
    {
        foreach ($fichiers as $chemin => $contenu) {
            @mkdir(dirname($this->depot.'/'.$chemin), 0755, true);
            file_put_contents($this->depot.'/'.$chemin, $contenu);
        }

        $redaction = (new RedacteurSoukdev(new VerificateurCompose, new EcrivainDocker))->rediger($this->depot);
        // Toute proposition doit passer le contrôle d'un soukdev.json écrit à la main.
        (new ValidateurSoukdev)->valider($redaction['manifeste']);

        return $redaction;
    }

    public function test_lit_le_service_web_et_son_port_dans_le_compose(): void
    {
        $redaction = $this->rediger([
            'docker-compose.yml' => "services:\n  db:\n    image: postgres:16\n  app:\n    build: .\n    expose:\n      - \"8000\"\n",
        ]);

        $this->assertSame(['nom' => 'app', 'port' => 8000], $redaction['manifeste']['service_web']);
        $this->assertSame('propre', $redaction['manifeste']['backend']);
        $this->assertContains('PostgreSQL dans docker-compose.yml (service « db ») : l\'appli apporte sa propre base (backend « propre »).', $redaction['deductions']);
        $this->assertSame([], $redaction['a_verifier']);
    }

    public function test_prend_le_port_du_conteneur_et_signale_les_ports_publies(): void
    {
        $redaction = $this->rediger([
            'docker-compose.yml' => "services:\n  web:\n    image: nginx:alpine\n    ports:\n      - \"127.0.0.1:8080:80/tcp\"\n",
        ]);

        $this->assertSame(['nom' => 'web', 'port' => 80], $redaction['manifeste']['service_web']);
        $this->assertStringContainsString('Souk.dev refusera ce docker-compose.yml en l\'état', $redaction['a_verifier'][0]);
        $this->assertStringContainsString('publie des ports', $redaction['a_verifier'][0]);
    }

    public function test_sans_port_dans_le_compose_lit_le_expose_de_la_derniere_etape_du_dockerfile(): void
    {
        $redaction = $this->rediger([
            'docker-compose.yml' => "services:\n  web:\n    build:\n      context: ./front\n",
            'front/Dockerfile' => "FROM node:22 AS build\nEXPOSE 5173\nFROM nginx:alpine\nEXPOSE 8080\n",
        ]);

        $this->assertSame(['nom' => 'web', 'port' => 8080], $redaction['manifeste']['service_web']);
        $this->assertContains('Service web : « web », port 8080, lu dans son Dockerfile (EXPOSE).', $redaction['deductions']);
    }

    public function test_sans_compose_devine_la_pile_et_dit_ce_qui_manque(): void
    {
        $redaction = $this->rediger([
            'package.json' => json_encode(['dependencies' => ['next' => '16.0.0']]),
        ]);

        $this->assertSame(['nom' => 'web', 'port' => 3000], $redaction['manifeste']['service_web']);
        $this->assertContains('Next.js détecté : port 3000 supposé. Service nommé « web ».', $redaction['deductions']);
        $this->assertStringStartsWith('docker-compose.yml est absent', $redaction['a_verifier'][0]);
        $this->assertSame([], $redaction['fichiers']);
        $this->assertContains('Le port 3000 est une supposition : mettez celui sur lequel votre service web écoute.', $redaction['a_verifier']);
    }

    public function test_sans_compose_ecrit_les_fichiers_docker_d_une_pile_sure(): void
    {
        $redaction = $this->rediger([
            'package.json' => json_encode(['dependencies' => ['vite' => '8.0.0'], 'scripts' => ['build' => 'vite build']]),
            'pnpm-lock.yaml' => '',
            '.env.example' => "VITE_TITRE=Ma boutique\n",
        ]);

        $this->assertSame(['Dockerfile', 'nginx.conf', '.dockerignore', 'docker-compose.yml'], array_keys($redaction['fichiers']));
        $this->assertSame(['nom' => 'web', 'port' => 80], $redaction['manifeste']['service_web']);
        $this->assertSame('Site Vite (pnpm, Node 22, dossier « dist ») : Souk.dev a écrit Dockerfile, nginx.conf, .dockerignore, docker-compose.yml, avec un service « web » sur le port 80.', $redaction['deductions'][0]);
        $this->assertStringContainsString('VITE_TITRE: ${VITE_TITRE}', $redaction['fichiers']['docker-compose.yml']);
        $this->assertStringStartsWith('Ces fichiers ne sont pas encore testés', $redaction['a_verifier'][0]);
    }

    public function test_avec_un_dockerfile_sans_compose_ecrit_seulement_le_compose(): void
    {
        $redaction = $this->rediger([
            'Dockerfile' => "FROM python:3.13-slim\nEXPOSE 8000\n",
            '.env.example' => "SECRET_KEY=\n",
        ]);

        $this->assertSame(['docker-compose.yml'], array_keys($redaction['fichiers']));
        $this->assertSame("services:\n  web:\n    build: .\n    restart: unless-stopped\n    expose:\n      - \"8000\"\n    environment:\n      SECRET_KEY: \${SECRET_KEY}\n", $redaction['fichiers']['docker-compose.yml']);
        $this->assertSame(['nom' => 'web', 'port' => 8000], $redaction['manifeste']['service_web']);
    }

    public function test_classe_chaque_variable_lue_dans_le_compose_et_le_env_exemple(): void
    {
        $redaction = $this->rediger([
            'docker-compose.yml' => "services:\n  web:\n    image: nginx:alpine\n    expose: [\"80\"]\n    env_file: .env\n    environment:\n      DB_PASSWORD: \${DB_PASSWORD:?à générer}\n      LOG_LEVEL: \${LOG_LEVEL:-info}\n      ECHAPPE: \$\$HOME\n",
            '.env.example' => implode("\n", [
                '# Nom affiché en haut de chaque page',
                'BOUTIQUE_NOM=',
                'STRIPE_SECRET_KEY=   # votre clé Stripe',
                'OPENAI_MODEL=gpt-5',
                'MAIL_PASSWORD=',
                'NEXT_PUBLIC_VAPID_PUBLIC_KEY=',
                'VERCEL_TOKEN=',
                'APP_ENV="production"',
                'SESSION_SECRET=changeme',
                'APP_URL=http://localhost:8000',
                'APP_KEY=',
                'SOUKDEV_CLE=reservee',
                'minuscule=ignoree',
            ]),
        ]);

        $this->assertSame([
            ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
            ['nom' => 'LOG_LEVEL', 'source' => 'fixe', 'valeur' => 'info'],
            ['nom' => 'BOUTIQUE_NOM', 'source' => 'client', 'libelle' => 'Nom affiché en haut de chaque page', 'requis' => true],
            ['nom' => 'STRIPE_SECRET_KEY', 'source' => 'client', 'libelle' => 'votre clé Stripe', 'requis' => false],
            ['nom' => 'OPENAI_MODEL', 'source' => 'fixe', 'valeur' => 'gpt-5'],
            ['nom' => 'MAIL_PASSWORD', 'source' => 'client', 'libelle' => 'Mail password (votre propre compte)', 'requis' => false],
            ['nom' => 'NEXT_PUBLIC_VAPID_PUBLIC_KEY', 'source' => 'client', 'libelle' => 'Next public vapid public key', 'requis' => true],
            ['nom' => 'VERCEL_TOKEN', 'source' => 'client', 'libelle' => 'Vercel token (votre propre compte)', 'requis' => false],
            ['nom' => 'APP_ENV', 'source' => 'fixe', 'valeur' => 'production'],
            ['nom' => 'SESSION_SECRET', 'source' => 'plateforme'],
            ['nom' => 'APP_URL', 'source' => 'fixe', 'valeur' => 'http://localhost:8000'],
        ], $redaction['manifeste']['variables']);
        $this->assertContains('APP_URL vaut « http://localhost:8000 » : en ligne, la copie n\'est pas sur localhost. Corrigez la valeur, ou demandez-la au client.', $redaction['a_verifier']);
        $this->assertStringStartsWith('APP_KEY n\'est pas déclarée', implode("\n", array_filter($redaction['a_verifier'], fn (string $ligne) => str_starts_with($ligne, 'APP_KEY'))));
    }

    public function test_sans_env_file_seules_les_variables_du_compose_comptent(): void
    {
        $redaction = $this->rediger([
            'docker-compose.yml' => "services:\n  web:\n    image: nginx:alpine\n    expose: [\"80\"]\n    environment:\n      TITRE: \$TITRE\n",
            '.env.example' => "NON_LUE=1\n",
        ]);

        $this->assertSame([
            ['nom' => 'TITRE', 'source' => 'client', 'libelle' => 'Titre', 'requis' => true],
        ], $redaction['manifeste']['variables']);
    }

    public function test_previent_quand_l_appli_depend_du_supabase_de_son_auteur(): void
    {
        $redaction = $this->rediger([
            'package.json' => json_encode(['dependencies' => ['next' => '16.0.0', '@supabase/ssr' => '0.12.0']]),
        ]);

        $this->assertContains('Cette appli dépend d\'un compte Supabase : chaque client devrait ouvrir le sien. Souk.dev ne fournit pas encore de Supabase par copie (prévu après le hackathon).', $redaction['a_verifier']);
    }

    public function test_un_compose_illisible_n_empeche_pas_la_proposition(): void
    {
        $redaction = $this->rediger([
            'docker-compose.yml' => "services: [\n",
            'index.html' => '<h1>Bonjour</h1>',
        ]);

        $this->assertSame(['nom' => 'web', 'port' => 80], $redaction['manifeste']['service_web']);
        $this->assertContains('docker-compose.yml est illisible (YAML invalide) : le service web a été deviné sans lui.', $redaction['a_verifier']);
    }

    public function test_ne_lit_pas_un_lien_vers_un_fichier_hors_du_depot(): void
    {
        $dehors = sys_get_temp_dir().'/soukdev-dehors-'.bin2hex(random_bytes(4)).'.env';
        file_put_contents($dehors, "MOT_DE_PASSE_DEHORS=fuite\n");
        symlink($dehors, $this->depot.'/.env.example');

        $redaction = $this->rediger(['index.html' => '<h1>Bonjour</h1>']);
        unlink($dehors);

        $this->assertSame([], $redaction['manifeste']['variables']);
    }

    public function test_le_texte_est_le_json_du_fichier_avec_une_fin_de_ligne(): void
    {
        $texte = RedacteurSoukdev::texte(['version' => 1, 'variables' => [['nom' => 'A', 'source' => 'fixe', 'valeur' => 'é/x']]]);

        $this->assertStringEndsWith("}\n", $texte);
        $this->assertStringContainsString('"valeur": "é/x"', $texte);
    }
}
