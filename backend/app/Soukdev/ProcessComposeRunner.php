<?php

declare(strict_types=1);

namespace App\Soukdev;

use Symfony\Component\Process\Process;

final class ProcessComposeRunner implements ComposeRunner
{
    public function up(string $project, string $directory): void
    {
        $this->guard($project, $directory);
        $this->run(self::upCommand($project, $directory), $directory, 300);
    }

    public function down(string $project, string $directory): void
    {
        $this->guard($project, $directory);
        $this->run(self::downCommand($project, $directory), $directory, 60);
    }

    /**
     * @return list<string>
     */
    public static function upCommand(string $project, string $directory): array
    {
        return ['docker', 'compose', '--project-name', $project, '--project-directory', $directory, 'up', '-d'];
    }

    /**
     * @return list<string>
     */
    public static function downCommand(string $project, string $directory): array
    {
        return ['docker', 'compose', '--project-name', $project, '--project-directory', $directory, 'down', '--volumes', '--remove-orphans'];
    }

    private function guard(string $project, string $directory): void
    {
        if (preg_match('/\A[a-z][a-z0-9_-]{0,40}\z/', $project) !== 1) {
            throw new LaunchFailedException('Nom de copie refusé.');
        }

        if (str_contains($directory, "\0") || str_contains($directory, "\n") || ! str_starts_with($directory, '/') || ! is_dir($directory)) {
            throw new LaunchFailedException('Dossier de copie refusé.');
        }
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command, string $directory, int $timeout): void
    {
        $process = new Process($command, $directory, ['COMPOSE_ANSI' => 'never'], null, $timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new LaunchFailedException;
        }
    }
}
