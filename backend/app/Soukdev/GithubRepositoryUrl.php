<?php

declare(strict_types=1);

namespace App\Soukdev;

final readonly class GithubRepositoryUrl
{
    private const string OWNER = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?';

    private function __construct(
        public string $owner,
        public string $repository,
        public ?string $branch,
    ) {}

    public static function parse(string $url): self
    {
        if (preg_match('~\Ahttps://github\.com/[^\s%\\\\@?#]+\z~', $url) !== 1) {
            throw new InvalidRepositoryException(['Adresse refusée.']);
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'github.com'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidRepositoryException(['Adresse refusée.']);
        }

        $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));

        if (count($segments) < 2) {
            throw new InvalidRepositoryException(['Adresse refusée.']);
        }

        $owner = $segments[0];
        $repository = $segments[1];

        if (str_ends_with($repository, '.git')) {
            $repository = substr($repository, 0, -4);
        }

        $branch = null;

        if (count($segments) > 2) {
            if ($segments[2] !== 'tree' || count($segments) < 4) {
                throw new InvalidRepositoryException(['Adresse refusée.']);
            }

            $branch = implode('/', array_slice($segments, 3));
        }

        if (preg_match('/\A'.self::OWNER.'\z/', $owner) !== 1 || ! self::validRepository($repository)) {
            throw new InvalidRepositoryException(['Adresse refusée.']);
        }

        if ($branch !== null && ! self::validBranch($branch)) {
            throw new InvalidRepositoryException(['Branche refusée.']);
        }

        return new self($owner, $repository, $branch);
    }

    public function cloneUrl(): string
    {
        return 'https://github.com/'.$this->owner.'/'.$this->repository.'.git';
    }

    private static function validRepository(string $repository): bool
    {
        return $repository !== ''
            && ! str_contains($repository, '..')
            && preg_match('/\A[A-Za-z0-9_][A-Za-z0-9._-]{0,99}\z/', $repository) === 1;
    }

    private static function validBranch(string $branch): bool
    {
        if (strlen($branch) > 255
            || str_starts_with($branch, '-')
            || str_starts_with($branch, '/')
            || str_ends_with($branch, '/')
            || str_ends_with($branch, '.')
            || str_contains($branch, '..')
            || str_contains($branch, '//')
            || str_contains($branch, '@')
            || str_contains($branch, ':')
        ) {
            return false;
        }

        return preg_match('#\A[A-Za-z0-9._/-]+\z#', $branch) === 1;
    }
}
