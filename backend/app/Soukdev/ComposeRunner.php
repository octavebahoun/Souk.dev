<?php

declare(strict_types=1);

namespace App\Soukdev;

interface ComposeRunner
{
    public function up(string $project, string $directory): void;

    public function down(string $project, string $directory): void;
}
