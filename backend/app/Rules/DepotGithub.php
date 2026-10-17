<?php

declare(strict_types=1);

namespace App\Rules;

use App\Soukdev\GithubRepositoryUrl;
use App\Soukdev\InvalidRepositoryException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class DepotGithub implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Adresse de dépôt invalide.');

            return;
        }

        try {
            GithubRepositoryUrl::parse($value);
        } catch (InvalidRepositoryException $exception) {
            $fail($exception->errors[0] ?? 'Adresse de dépôt invalide.');
        }
    }
}
