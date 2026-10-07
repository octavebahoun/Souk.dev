<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Soukdev\ComposeRunner;
use App\Soukdev\CopyLauncher;
use App\Soukdev\EnvGenerator;
use App\Soukdev\GitCloner;
use App\Soukdev\InvalidClientValuesException;
use App\Soukdev\InvalidRepositoryException;
use App\Soukdev\LaunchFailedException;
use App\Soukdev\ProcessComposeRunner;
use App\Soukdev\RepositoryReader;
use App\Soukdev\SchemaValidator;
use App\Soukdev\SecretGenerator;
use PHPUnit\Framework\TestCase;

class CopyLauncherTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->deleteTree($directory);
        }

        parent::tearDown();
    }

    public function test_il_garde_le_depot_et_lance_compose_avec_le_projet(): void
    {
        $compose = $this->compose();
        $copies = $this->directory();
        $copy = $this->launcher($this->clonerWithFiles(), $compose, $copies)->launch(
            'https://github.com/acme/pharmacie',
            ['APP_NOM' => 'Pharmacie'],
        );

        $this->assertMatchesRegularExpression('/\Ac[0-9a-f]{16}\z/', $copy->id);
        $this->assertSame($copies.'/'.$copy->id, $copy->directory);
        $this->assertDirectoryExists($copy->directory);
        $this->assertSame('front', $copy->manifest->serviceWeb);
        $this->assertSame(
            "APP_NOM=Pharmacie\nDB_PASSWORD=plateforme-secret\nDEVISE=FCFA\n",
            $compose->envDuringUp,
        );
        $this->assertSame(file_get_contents($copy->directory.'/.env'), $compose->envDuringUp);
        $this->assertSame(0600, fileperms($copy->directory.'/.env') & 0777);
        $this->assertSame([['up', $copy->id, $copy->directory]], $compose->calls);
    }

    public function test_il_efface_la_copie_si_une_valeur_client_manque(): void
    {
        $compose = $this->compose();
        $copies = $this->directory();

        try {
            $this->launcher($this->clonerWithFiles(), $compose, $copies)->launch(
                'https://github.com/acme/pharmacie',
                [],
            );
            $this->fail('La valeur client manquante aurait dû arrêter le lancement.');
        } catch (InvalidClientValuesException $exception) {
            $this->assertSame(['APP_NOM est requis.'], $exception->errors);
        }

        $this->assertSame([], $compose->calls);
        $this->assertSame([], $this->children($copies));
    }

    public function test_il_arrete_et_efface_la_copie_si_compose_echoue(): void
    {
        $compose = $this->compose(failUp: true);
        $copies = $this->directory();

        try {
            $this->launcher($this->clonerWithFiles(), $compose, $copies)->launch(
                'https://github.com/acme/pharmacie',
                ['APP_NOM' => 'Pharmacie'],
            );
            $this->fail('L\'échec de Compose aurait dû arrêter le lancement.');
        } catch (LaunchFailedException $exception) {
            $this->assertSame('Impossible de lancer la copie.', $exception->getMessage());
        }

        $this->assertCount(2, $compose->calls);
        $this->assertSame('up', $compose->calls[0][0]);
        $this->assertSame('down', $compose->calls[1][0]);
        $this->assertSame($compose->calls[0][1], $compose->calls[1][1]);
        $this->assertSame([], $this->children($copies));
    }

    public function test_il_efface_la_copie_si_ladresse_est_refusee(): void
    {
        $compose = $this->compose();
        $copies = $this->directory();

        try {
            $this->launcher($this->clonerWithFiles(), $compose, $copies)->launch('http://github.com/acme/pharmacie', []);
            $this->fail('L\'adresse aurait dû être refusée.');
        } catch (InvalidRepositoryException $exception) {
            $this->assertSame(['Adresse refusée.'], $exception->errors);
        }

        $this->assertSame([], $compose->calls);
        $this->assertSame([], $this->children($copies));
    }

    public function test_la_commande_compose_isole_le_projet(): void
    {
        $directory = '/tmp/soukdev-copie';

        $this->assertSame(
            ['docker', 'compose', '--project-name', 'c0123456789abcdef0', '--project-directory', $directory, 'up', '-d'],
            ProcessComposeRunner::upCommand('c0123456789abcdef0', $directory),
        );
        $this->assertSame(
            ['docker', 'compose', '--project-name', 'c0123456789abcdef0', '--project-directory', $directory, 'down', '--volumes', '--remove-orphans'],
            ProcessComposeRunner::downCommand('c0123456789abcdef0', $directory),
        );
    }

    public function test_le_lanceur_compose_refuse_un_nom_de_projet_injecte(): void
    {
        $runner = new ProcessComposeRunner;

        try {
            $runner->up('copie;rm', '/tmp');
            $this->fail('Le nom de projet aurait dû être refusé.');
        } catch (LaunchFailedException $exception) {
            $this->assertSame('Nom de copie refusé.', $exception->getMessage());
        }
    }

    private function launcher(GitCloner $git, ComposeRunner $compose, string $copies): CopyLauncher
    {
        return new CopyLauncher(
            new RepositoryReader($git, new SchemaValidator(dirname(__DIR__, 3).'/soukdev.schema.json'), $copies.'/lectures'),
            new EnvGenerator(new class implements SecretGenerator
            {
                public function generate(): string
                {
                    return 'plateforme-secret';
                }
            }),
            $compose,
            $copies,
        );
    }

    private function clonerWithFiles(): GitCloner
    {
        $json = (string) json_encode([
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom', 'requis' => true],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
                ['nom' => 'DEVISE', 'source' => 'fixe', 'valeur' => 'FCFA'],
            ],
        ], JSON_THROW_ON_ERROR);

        return new class($json) implements GitCloner
        {
            public function __construct(private string $json) {}

            public function checkout(string $remote, ?string $branch, string $destination): void
            {
                mkdir($destination);
                file_put_contents($destination.'/docker-compose.yml', "services:\n  front:\n    image: nginx\n");
                file_put_contents($destination.'/soukdev.json', $this->json);
            }
        };
    }

    private function compose(bool $failUp = false): ComposeRunner
    {
        return new class($failUp) implements ComposeRunner
        {
            /** @var list<array{0: string, 1: string, 2: string}> */
            public array $calls = [];

            public ?string $envDuringUp = null;

            public function __construct(private bool $failUp) {}

            public function up(string $project, string $directory): void
            {
                $this->calls[] = ['up', $project, $directory];
                $env = file_get_contents($directory.'/.env');
                $this->envDuringUp = $env === false ? null : $env;

                if ($this->failUp) {
                    throw new LaunchFailedException;
                }
            }

            public function down(string $project, string $directory): void
            {
                $this->calls[] = ['down', $project, $directory];
            }
        };
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir().'/soukdev-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    /**
     * @return list<string>
     */
    private function children(string $directory): array
    {
        $names = scandir($directory);

        if ($names === false) {
            return [];
        }

        return array_values(array_filter($names, fn (string $name): bool => $name !== '.' && $name !== '..'));
    }

    private function deleteTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isLink() || ! $file->isDir()) {
                unlink($file->getPathname());
            } else {
                rmdir($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
