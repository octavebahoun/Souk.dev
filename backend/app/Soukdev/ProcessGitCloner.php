<?php

declare(strict_types=1);

namespace App\Soukdev;

use Symfony\Component\Process\Process;

final class ProcessGitCloner implements GitCloner
{
    private const array GIT_ENV = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_SYSTEM' => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
    ];

    public function checkout(string $remote, ?string $branch, string $destination): void
    {
        $this->assertRemote($remote);
        $this->assertBranch($branch);

        $command = ['git', 'clone', '--depth', '1', '--quiet'];

        if ($branch !== null) {
            $command[] = '--branch';
            $command[] = $branch;
        }

        $command[] = '--';
        $command[] = $remote;
        $command[] = $destination;

        $process = new Process($command, null, self::GIT_ENV, null, 60);
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        $output = $process->getErrorOutput()."\n".$process->getOutput();

        if (str_contains($output, 'Remote branch') || str_contains($output, 'Could not find remote branch')) {
            throw new InvalidRepositoryException(['Branche introuvable.']);
        }

        if (preg_match('/not found|does not exist|does not appear to be a git repository|Could not read from remote/i', $output) === 1) {
            throw new InvalidRepositoryException(['Dépôt introuvable.']);
        }

        throw new InvalidRepositoryException(['Impossible de cloner le dépôt.']);
    }

    private function assertRemote(string $remote): void
    {
        if (str_contains($remote, '..') || str_contains($remote, "\0") || str_contains($remote, "\n") || str_starts_with($remote, '-')) {
            throw new InvalidRepositoryException(['Adresse refusée.']);
        }

        if (preg_match('#\Ahttps://github\.com/[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?/[A-Za-z0-9_][A-Za-z0-9._-]{0,99}\.git\z#', $remote) === 1) {
            return;
        }

        if (preg_match('#\Afile://(/[A-Za-z0-9._/-]+)\z#', $remote) === 1) {
            return;
        }

        throw new InvalidRepositoryException(['Adresse refusée.']);
    }

    private function assertBranch(?string $branch): void
    {
        if ($branch === null) {
            return;
        }

        if (str_starts_with($branch, '-')
            || str_contains($branch, '..')
            || preg_match('#\A[A-Za-z0-9._/-]+\z#', $branch) !== 1
        ) {
            throw new InvalidRepositoryException(['Branche refusée.']);
        }
    }
}
