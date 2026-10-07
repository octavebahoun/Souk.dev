<?php

declare(strict_types=1);

namespace App\Soukdev;

use RuntimeException;
use Throwable;

final class CopyLauncher
{
    public function __construct(
        private readonly RepositoryReader $reader,
        private readonly EnvGenerator $env,
        private readonly ComposeRunner $compose,
        private readonly string $copiesDirectory,
    ) {}

    /**
     * Clone le dépôt, écrit le .env, puis lance une copie isolée
     * avec docker compose -p. Le dossier reste en place tant que la copie tourne.
     *
     * @param  array<string, string>  $valeursClient
     */
    public function launch(string $url, array $valeursClient): RunningCopy
    {
        if (! is_dir($this->copiesDirectory) && ! mkdir($this->copiesDirectory, 0700, true) && ! is_dir($this->copiesDirectory)) {
            throw new RuntimeException('Dossier des copies inaccessible.');
        }

        $id = 'c'.bin2hex(random_bytes(8));
        $destination = $this->copiesDirectory.'/'.$id;
        $started = false;

        try {
            $manifest = $this->reader->checkoutInto($url, $destination);
            $this->writeEnv($destination, $this->env->generate($manifest, $valeursClient));
            $started = true;
            $this->compose->up($id, $destination);

            return new RunningCopy($id, $destination, $manifest);
        } catch (Throwable $exception) {
            $this->rollback($id, $destination, $started);

            throw $exception;
        }
    }

    private function writeEnv(string $directory, string $contents): void
    {
        $path = $directory.'/.env';

        if (is_link($path)) {
            unlink($path);
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new LaunchFailedException('Impossible d\'écrire le .env de la copie.');
        }

        chmod($path, 0600);
    }

    private function rollback(string $id, string $destination, bool $started): void
    {
        if ($started && is_dir($destination)) {
            try {
                $this->compose->down($id, $destination);
            } catch (Throwable) {
                // La copie n'a peut-être pas eu le temps de démarrer.
            }
        }

        DirectoryRemover::remove($this->copiesDirectory, $destination);
    }
}
