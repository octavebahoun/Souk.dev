<?php

declare(strict_types=1);

namespace App\Soukdev;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class DirectoryRemover
{
    public static function remove(string $root, string $directory): void
    {
        $work = realpath($root);
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
