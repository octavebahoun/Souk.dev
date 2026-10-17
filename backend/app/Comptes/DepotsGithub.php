<?php

declare(strict_types=1);

namespace App\Comptes;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DepotsGithub
{
    /**
     * @return list<array{nom: string, description: string|null, url: string, etoiles: int, langage: string|null, readme: string|null}>
     */
    public function pour(?string $username): array
    {
        if ($username === null || $username === '' || preg_match('/\A[A-Za-z0-9][A-Za-z0-9-]{0,38}\z/', $username) !== 1) {
            return [];
        }

        try {
            $reponse = Http::withHeaders($this->entetes())
                ->timeout(5)
                ->get('https://api.github.com/users/'.rawurlencode($username).'/repos', [
                    'per_page' => 10,
                    'sort' => 'updated',
                    'type' => 'owner',
                ]);
        } catch (Throwable) {
            return [];
        }

        if (! $reponse->successful() || ! is_array($reponse->json())) {
            return [];
        }

        $depots = [];

        foreach ($reponse->json() as $depot) {
            if (! is_array($depot) || ! is_string($depot['name'] ?? null) || ! is_string($depot['html_url'] ?? null)) {
                continue;
            }

            $complet = is_string($depot['full_name'] ?? null) ? $depot['full_name'] : '';

            $depots[] = [
                'nom' => $depot['name'],
                'description' => is_string($depot['description'] ?? null) ? $depot['description'] : null,
                'url' => $depot['html_url'],
                'etoiles' => (int) ($depot['stargazers_count'] ?? 0),
                'langage' => is_string($depot['language'] ?? null) ? $depot['language'] : null,
                'readme' => null,
                'complet' => preg_match('#\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z#', $complet) === 1 ? $complet : '',
            ];
        }

        return $this->avecReadmes($depots);
    }

    /**
     * @param  list<array{nom: string, description: string|null, url: string, etoiles: int, langage: string|null, readme: string|null, complet: string}>  $depots
     * @return list<array{nom: string, description: string|null, url: string, etoiles: int, langage: string|null, readme: string|null}>
     */
    private function avecReadmes(array $depots): array
    {
        $aLire = array_filter($depots, fn (array $depot): bool => $depot['complet'] !== '');

        if ($aLire !== []) {
            try {
                $reponses = Http::pool(function (Pool $pool) use ($aLire): void {
                    foreach ($aLire as $index => $depot) {
                        $pool->as((string) $index)
                            ->withHeaders($this->entetes('application/vnd.github.raw'))
                            ->timeout(5)
                            ->get('https://api.github.com/repos/'.$depot['complet'].'/readme');
                    }
                });
            } catch (Throwable) {
                $reponses = [];
            }

            foreach ($reponses as $index => $reponse) {
                if ($reponse instanceof Throwable || ! $reponse->successful()) {
                    continue;
                }

                $corps = trim($reponse->body());
                $depots[(int) $index]['readme'] = $corps === '' ? null : $corps;
            }
        }

        return array_map(function (array $depot): array {
            unset($depot['complet']);

            return $depot;
        }, $depots);
    }

    /**
     * @return array<string, string>
     */
    private function entetes(string $accept = 'application/vnd.github+json'): array
    {
        return [
            'User-Agent' => 'Souk.dev',
            'Accept' => $accept,
        ];
    }
}
