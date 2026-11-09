<?php

namespace Tests\Unit\Moteur;

use App\Moteur\EcrivainDocker;
use App\Moteur\VerificateurCompose;
use PHPUnit\Framework\TestCase;

class EcrivainDockerTest extends TestCase
{
    private string $depot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depot = sys_get_temp_dir().'/soukdev-docker-'.bin2hex(random_bytes(4));
        mkdir($this->depot);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->depot));

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $fichiers
     * @param  list<string>  $variables
     */
    private function ecrire(array $fichiers, array $variables = []): ?array
    {
        foreach ($fichiers as $chemin => $contenu) {
            @mkdir(dirname($this->depot.'/'.$chemin), 0755, true);
            file_put_contents($this->depot.'/'.$chemin, $contenu);
        }

        $ecrit = (new EcrivainDocker)->ecrire($this->depot, $variables);

        if ($ecrit !== null) {
            // Ce que Souk.dev écrit passe toujours les règles de la publication.
            (new VerificateurCompose)->verifier($ecrit['fichiers']['docker-compose.yml']);
        }

        return $ecrit;
    }

    private static function paquet(array $dependances, array $scripts, array $autres = []): string
    {
        return json_encode(['dependencies' => $dependances, 'scripts' => $scripts, ...$autres], JSON_THROW_ON_ERROR);
    }

    public function test_un_site_statique_est_servi_par_nginx(): void
    {
        $ecrit = $this->ecrire(['index.html' => '<h1>Bonjour</h1>']);

        $this->assertSame(80, $ecrit['port']);
        $this->assertSame(['Dockerfile', '.dockerignore', 'docker-compose.yml'], array_keys($ecrit['fichiers']));
        $this->assertStringContainsString("COPY . /usr/share/nginx/html\n", $ecrit['fichiers']['Dockerfile']);
        // L'historique Git ne doit jamais être servi avec le site.
        $this->assertStringStartsWith(".git\n", $ecrit['fichiers']['.dockerignore']);
    }

    public function test_un_site_vite_avec_pnpm_est_construit_puis_servi_avec_repli_sur_index(): void
    {
        $ecrit = $this->ecrire([
            'package.json' => self::paquet(['vite' => '8.0.0'], ['build' => 'vite build'], ['engines' => ['node' => '>=20']]),
            'pnpm-lock.yaml' => "lockfileVersion: '9.0'\n",
            'vite.config.js' => "export default { build: { outDir: './public-build' } }\n",
        ], ['VITE_API_URL', 'SECRET_SERVEUR']);

        $dockerfile = $ecrit['fichiers']['Dockerfile'];
        $this->assertSame('Site Vite (pnpm, Node 20, dossier « public-build »)', $ecrit['pile']);
        $this->assertStringContainsString("FROM node:20-slim AS construction\n", $dockerfile);
        $this->assertStringContainsString("ARG VITE_API_URL\nRUN corepack enable\n", $dockerfile);
        $this->assertStringContainsString("RUN pnpm install --frozen-lockfile\nRUN pnpm run build\n", $dockerfile);
        $this->assertStringContainsString('COPY --from=construction /app/public-build /usr/share/nginx/html', $dockerfile);
        $this->assertStringContainsString('try_files $uri $uri/ /index.html;', $ecrit['fichiers']['nginx.conf']);
        // Une variable VITE_ est lue à la construction ; un site statique n'a pas d'environnement.
        $this->assertSame("services:\n  web:\n    build:\n      context: .\n      args:\n        VITE_API_URL: \${VITE_API_URL}\n    restart: unless-stopped\n    expose:\n      - \"80\"\n", $ecrit['fichiers']['docker-compose.yml']);
    }

    public function test_create_react_app_avec_npm_sort_dans_build(): void
    {
        $ecrit = $this->ecrire([
            'package.json' => self::paquet(['react-scripts' => '5.0.1'], ['build' => 'react-scripts build']),
            'package-lock.json' => '{}',
        ]);

        $this->assertStringContainsString("RUN npm ci\nRUN npm run build\n", $ecrit['fichiers']['Dockerfile']);
        $this->assertStringContainsString('COPY --from=construction /app/build /usr/share/nginx/html', $ecrit['fichiers']['Dockerfile']);
        $this->assertStringNotContainsString('corepack', $ecrit['fichiers']['Dockerfile']);
    }

    public function test_next_avec_yarn_est_lance_sur_le_port_3000(): void
    {
        $ecrit = $this->ecrire([
            'package.json' => self::paquet(['next' => '16.0.0', 'vite' => '8.0.0'], ['build' => 'next build', 'start' => 'next start']),
            'yarn.lock' => '',
            '.nvmrc' => "v24.1.0\n",
        ], ['NEXT_PUBLIC_SITE', 'DATABASE_URL']);

        $this->assertSame(3000, $ecrit['port']);
        $this->assertSame('Next.js (Yarn, Node 24)', $ecrit['pile']);
        $this->assertStringContainsString("CMD [\"yarn\",\"run\",\"start\"]\n", $ecrit['fichiers']['Dockerfile']);
        $this->assertStringContainsString('ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0', $ecrit['fichiers']['Dockerfile']);
        $this->assertStringContainsString("      args:\n        NEXT_PUBLIC_SITE: \${NEXT_PUBLIC_SITE}\n", $ecrit['fichiers']['docker-compose.yml']);
        $this->assertStringContainsString("    environment:\n      DATABASE_URL: \${DATABASE_URL}\n", $ecrit['fichiers']['docker-compose.yml']);
    }

    public function test_un_serveur_express_prend_le_port_lu_dans_son_code(): void
    {
        $ecrit = $this->ecrire([
            'package.json' => self::paquet(['express' => '5.0.0'], ['start' => 'node src/server.js']),
            'src/server.js' => "const port = process.env.PORT || 4100\napp.listen(port)\n",
        ], ['DB_PASSWORD']);

        $this->assertSame(4100, $ecrit['port']);
        $this->assertSame(['Port 4100, lu dans src/server.js.'], $ecrit['notes']);
        $this->assertStringContainsString("ENV NODE_ENV=production PORT=4100\nEXPOSE 4100\nCMD [\"npm\",\"run\",\"start\"]\n", $ecrit['fichiers']['Dockerfile']);
        $this->assertStringContainsString("      - \"4100\"\n    environment:\n      DB_PASSWORD: \${DB_PASSWORD}\n", $ecrit['fichiers']['docker-compose.yml']);
    }

    /** Pas de fichier deviné hors des piles sûres : mieux vaut dire ce qui manque. */
    public function test_ne_devine_rien_hors_des_piles_sures(): void
    {
        $this->assertNull($this->ecrire(['composer.json' => '{"require":{"laravel/framework":"^13"}}']));
        $this->assertNull($this->ecrire(['package.json' => self::paquet(['vite' => '8.0.0'], ['build' => 'vite build']), 'bun.lockb' => '']));
    }

    public function test_un_paquet_sans_script_de_build_n_est_pas_devine(): void
    {
        $this->assertNull($this->ecrire(['package.json' => self::paquet(['vite' => '8.0.0'], ['dev' => 'vite'])]));
    }
}
