<?php

declare(strict_types=1);

namespace App\Soukdev;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class RepositoryReader
{
    private const int MAX_MANIFEST_BYTES = 65536;

    public function __construct(
        private readonly GitCloner $git,
        private readonly SchemaValidator $validator,
        private readonly string $workDirectory,
    ) {}

    public function read(string $url): Manifest
    {
        $repository = GithubRepositoryUrl::parse($url);

        if (! is_dir($this->workDirectory) && ! mkdir($this->workDirectory, 0700, true) && ! is_dir($this->workDirectory)) {
            throw new RuntimeException('Dossier de travail inaccessible.');
        }

        $destination = $this->workDirectory.'/'.bin2hex(random_bytes(16));

        try {
            $this->git->checkout($repository->cloneUrl(), $repository->branch, $destination);

            return $this->manifestFrom($destination);
        } finally {
            $this->deleteDirectory($destination);
        }
    }

    private function manifestFrom(string $directory): Manifest
    {
        $errors = [];
        $compose = $this->regularFile($directory, 'docker-compose.yml');
        $manifest = $this->regularFile($directory, 'soukdev.json');

        if ($compose === null) {
            $errors[] = 'docker-compose.yml est absent à la racine du dépôt.';
        }

        if ($manifest === null) {
            $errors[] = 'soukdev.json est absent à la racine du dépôt.';
        } elseif (filesize($manifest) > self::MAX_MANIFEST_BYTES) {
            $errors[] = 'soukdev.json est trop volumineux.';
        }

        if ($errors !== []) {
            throw new InvalidRepositoryException($errors);
        }

        $json = file_get_contents($manifest);

        if ($json === false) {
            throw new InvalidRepositoryException(['soukdev.json est illisible.']);
        }

        return $this->validator->validate($json);
    }

    private function regularFile(string $directory, string $name): ?string
    {
        $path = $directory.'/'.$name;

        if (is_link($path) || ! is_file($path)) {
            return null;
        }

        $directoryReal = realpath($directory);
        $fileReal = realpath($path);

        if ($directoryReal === false || $fileReal === false || ! str_starts_with($fileReal, $directoryReal.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $fileReal;
    }

    private function deleteDirectory(string $directory): void
    {
        $work = realpath($this->workDirectory);
        $target = is_dir($directory) ? realpath($directory) : false;

        if ($work === false || $target === false || ! str_starts_with($target, $work.DIRECTORY_SEPARATOR)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isLink() || ! $file->isDir()) {
                unlink($file->getPathname());
            } else {
                rmdir($file->getPathname());
            }
        }

        rmdir($target);
    }
}
