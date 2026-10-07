<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Soukdev\GitCloner;
use App\Soukdev\GithubRepositoryUrl;
use App\Soukdev\InvalidManifestException;
use App\Soukdev\InvalidRepositoryException;
use App\Soukdev\ProcessGitCloner;
use App\Soukdev\RepositoryReader;
use App\Soukdev\SchemaValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class RepositoryReaderTest extends TestCase
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

    public function test_il_accepte_une_adresse_github_publique(): void
    {
        $url = GithubRepositoryUrl::parse('https://github.com/acme/pharmacie.git');

        $this->assertSame('acme', $url->owner);
        $this->assertSame('pharmacie', $url->repository);
        $this->assertNull($url->branch);
        $this->assertSame('https://github.com/acme/pharmacie.git', $url->cloneUrl());
    }

    public function test_il_lit_la_branche_dans_ladresse(): void
    {
        $url = GithubRepositoryUrl::parse('https://github.com/acme/pharmacie/tree/feature/momo/');

        $this->assertSame('feature/momo', $url->branch);
        $this->assertSame('https://github.com/acme/pharmacie.git', $url->cloneUrl());
    }

    public function test_il_refuse_une_adresse_qui_nest_pas_un_depot_github_public(): void
    {
        $urls = [
            'http://github.com/acme/pharmacie',
            'git@github.com:acme/pharmacie.git',
            'https://user:token@github.com/acme/pharmacie',
            'https://gitlab.com/acme/pharmacie',
            'https://github.com/acme/pharmacie?ref=main',
            'https://github.com/acme/pharmacie/blob/main/soukdev.json',
            "https://github.com/acme/pharmacie\n",
            'https://github.com/acme',
        ];

        foreach ($urls as $url) {
            try {
                GithubRepositoryUrl::parse($url);
                $this->fail($url);
            } catch (InvalidRepositoryException $exception) {
                $this->assertSame(['Adresse refusée.'], $exception->errors, $url);
            }
        }
    }

    public function test_il_refuse_une_branche_dangereuse(): void
    {
        foreach ([
            'https://github.com/acme/pharmacie/tree/-main',
            'https://github.com/acme/pharmacie/tree/foo/../../x',
        ] as $url) {
            try {
                GithubRepositoryUrl::parse($url);
                $this->fail($url);
            } catch (InvalidRepositoryException $exception) {
                $this->assertSame(['Branche refusée.'], $exception->errors, $url);
            }
        }
    }

    public function test_il_clone_un_depot_local_en_superficiel(): void
    {
        $origin = $this->repository([
            'soukdev.json' => $this->manifestJson(),
            'docker-compose.yml' => "services:\n  front:\n    image: nginx\n",
        ]);
        $destination = $this->path();

        (new ProcessGitCloner)->checkout('file://'.$origin, 'main', $destination);

        $shallow = new Process(['git', '-C', $destination, 'rev-parse', '--is-shallow-repository']);
        $shallow->mustRun();

        $this->assertSame("true\n", $shallow->getOutput());
        $this->assertFileExists($destination.'/soukdev.json');
        $this->assertFileExists($destination.'/docker-compose.yml');
    }

    public function test_il_signale_un_depot_ou_une_branche_introuvable(): void
    {
        $origin = $this->repository([
            'soukdev.json' => $this->manifestJson(),
            'docker-compose.yml' => "services: {}\n",
        ]);
        $cloner = new ProcessGitCloner;

        try {
            $cloner->checkout('file://'.$this->directory().'/absent', null, $this->path());
            $this->fail('Le dépôt absent aurait dû être refusé.');
        } catch (InvalidRepositoryException $exception) {
            $this->assertSame(['Dépôt introuvable.'], $exception->errors);
        }

        try {
            $cloner->checkout('file://'.$origin, 'absente', $this->path());
            $this->fail('La branche absente aurait dû être refusée.');
        } catch (InvalidRepositoryException $exception) {
            $this->assertSame(['Branche introuvable.'], $exception->errors);
        }
    }

    public function test_il_refuse_une_adresse_de_clone_qui_nest_pas_github(): void
    {
        try {
            (new ProcessGitCloner)->checkout('https://evil.example/acme/app.git', null, $this->directory());
            $this->fail('L\'adresse aurait dû être refusée.');
        } catch (InvalidRepositoryException $exception) {
            $this->assertSame(['Adresse refusée.'], $exception->errors);
        }
    }

    public function test_il_lit_le_manifeste_dun_depot_git_local(): void
    {
        $origin = $this->repository([
            'soukdev.json' => $this->manifestJson(),
            'docker-compose.yml' => "services:\n  front:\n    image: nginx\n",
        ]);
        $work = $this->directory();
        $spy = new class($origin) implements GitCloner
        {
            public ?string $remote = null;

            public ?string $branch = null;

            public ?string $destination = null;

            public function __construct(private string $origin) {}

            public function checkout(string $remote, ?string $branch, string $destination): void
            {
                $this->remote = $remote;
                $this->branch = $branch;
                $this->destination = $destination;
                (new ProcessGitCloner)->checkout('file://'.$this->origin, $branch, $destination);
            }
        };

        $manifest = $this->reader($spy, $work)->read('https://github.com/acme/pharmacie/tree/main');

        $this->assertSame('https://github.com/acme/pharmacie.git', $spy->remote);
        $this->assertSame('main', $spy->branch);
        $this->assertSame('front', $manifest->serviceWeb);
        $this->assertSame(3000, $manifest->port);
        $this->assertSame('propre', $manifest->backend);
        $this->assertNotNull($spy->destination);
        $this->assertDirectoryDoesNotExist($spy->destination);
    }

    public function test_il_signale_les_fichiers_absents_et_nettoie_le_dossier(): void
    {
        $work = $this->directory();
        $spy = $this->clonerThatWrites([]);

        try {
            $this->reader($spy, $work)->read('https://github.com/acme/pharmacie');
            $this->fail('Les fichiers absents auraient dû être signalés.');
        } catch (InvalidRepositoryException $exception) {
            $this->assertSame([
                'docker-compose.yml est absent à la racine du dépôt.',
                'soukdev.json est absent à la racine du dépôt.',
            ], $exception->errors);
        }

        $this->assertNotNull($spy->destination);
        $this->assertDirectoryDoesNotExist($spy->destination);
    }

    public function test_il_refuse_un_lien_a_la_place_du_manifeste(): void
    {
        $outside = $this->directory().'/secret.json';
        file_put_contents($outside, $this->manifestJson());
        $work = $this->directory();
        $spy = $this->clonerThatWrites([
            'docker-compose.yml' => "services: {}\n",
        ], ['soukdev.json' => $outside]);

        try {
            $this->reader($spy, $work)->read('https://github.com/acme/pharmacie');
            $this->fail('Un lien symbolique aurait dû être refusé.');
        } catch (InvalidRepositoryException $exception) {
            $this->assertSame(['soukdev.json est absent à la racine du dépôt.'], $exception->errors);
        }
    }

    public function test_il_transmet_un_manifeste_invalide(): void
    {
        $spy = $this->clonerThatWrites([
            'docker-compose.yml' => "services: {}\n",
            'soukdev.json' => '{',
        ]);

        try {
            $this->reader($spy, $this->directory())->read('https://github.com/acme/pharmacie');
            $this->fail('Le manifeste invalide aurait dû être refusé.');
        } catch (InvalidManifestException $exception) {
            $this->assertSame(['Le fichier n\'est pas du JSON valide.'], $exception->errors);
        }

        $this->assertNotNull($spy->destination);
        $this->assertDirectoryDoesNotExist($spy->destination);
    }

    public function test_il_refuse_un_manifeste_trop_volumineux(): void
    {
        $spy = $this->clonerThatWrites([
            'docker-compose.yml' => "services: {}\n",
            'soukdev.json' => str_repeat('a', 65537),
        ]);

        try {
            $this->reader($spy, $this->directory())->read('https://github.com/acme/pharmacie');
            $this->fail('Le manifeste trop volumineux aurait dû être refusé.');
        } catch (InvalidRepositoryException $exception) {
            $this->assertSame(['soukdev.json est trop volumineux.'], $exception->errors);
        }
    }

    private function reader(GitCloner $git, string $work): RepositoryReader
    {
        return new RepositoryReader(
            $git,
            new SchemaValidator(dirname(__DIR__, 3).'/soukdev.schema.json'),
            $work,
        );
    }

    /**
     * @param  array<string, string>  $files
     * @param  array<string, string>  $links
     */
    private function clonerThatWrites(array $files, array $links = []): GitCloner
    {
        return new class($files, $links) implements GitCloner
        {
            public ?string $destination = null;

            /**
             * @param  array<string, string>  $files
             * @param  array<string, string>  $links
             */
            public function __construct(private array $files, private array $links) {}

            public function checkout(string $remote, ?string $branch, string $destination): void
            {
                $this->destination = $destination;
                mkdir($destination);

                foreach ($this->files as $name => $contents) {
                    file_put_contents($destination.'/'.$name, $contents);
                }

                foreach ($this->links as $name => $target) {
                    symlink($target, $destination.'/'.$name);
                }
            }
        };
    }

    /**
     * @param  array<string, string>  $files
     */
    private function repository(array $files): string
    {
        $directory = $this->directory();
        $this->git($directory, ['init', '-b', 'main']);

        foreach ($files as $name => $contents) {
            file_put_contents($directory.'/'.$name, $contents);
        }

        $this->git($directory, ['add', '.']);
        $this->git($directory, ['-c', 'user.email=test@example.com', '-c', 'user.name=Test', 'commit', '-m', 'init']);

        return $directory;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(string $directory, array $arguments): void
    {
        $process = new Process(['git', '-C', $directory, ...$arguments]);
        $process->mustRun();
    }

    private function directory(): string
    {
        $directory = $this->path();
        mkdir($directory);

        return $directory;
    }

    private function path(): string
    {
        $directory = sys_get_temp_dir().'/soukdev-'.bin2hex(random_bytes(8));
        $this->directories[] = $directory;

        return $directory;
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

    private function manifestJson(): string
    {
        return (string) json_encode([
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom', 'requis' => true],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
