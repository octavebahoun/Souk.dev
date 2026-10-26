<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;
use Tests\TestCase;

/**
 * La CI bloque une PR qui s'écarte du contrat : chaque route de l'API existe dans
 * openapi.yaml, et chaque route du contrat est codée (sauf celles listées ci-dessous).
 */
class ContratRoutesTest extends TestCase
{
    /**
     * Routes du contrat volontairement pas encore codées (hors MVP).
     * On retire une ligne dès que la route existe.
     */
    private const PAS_ENCORE_CODEES = [];

    public function test_chaque_route_de_l_api_est_dans_le_contrat(): void
    {
        $horsContrat = array_values(array_diff($this->routesApi(), $this->routesContrat()));

        $this->assertSame([], $horsContrat, 'Routes Laravel absentes de openapi.yaml : ajoutez-les au contrat.');
    }

    public function test_chaque_route_du_contrat_est_codee(): void
    {
        $codees = [...$this->routesApi(), ...$this->routesWeb()];
        $manquantes = array_values(array_diff($this->routesContrat(), $codees, self::PAS_ENCORE_CODEES));

        $this->assertSame([], $manquantes, 'Routes de openapi.yaml sans route Laravel.');
    }

    public function test_la_liste_des_routes_pas_encore_codees_est_a_jour(): void
    {
        $codees = [...$this->routesApi(), ...$this->routesWeb()];

        $this->assertSame([], array_values(array_intersect(self::PAS_ENCORE_CODEES, $codees)), 'Route codée : retirez-la de PAS_ENCORE_CODEES.');
        $this->assertSame([], array_values(array_diff(self::PAS_ENCORE_CODEES, $this->routesContrat())), 'Route absente du contrat : retirez-la de PAS_ENCORE_CODEES.');
    }

    /**
     * Les routes sous /api, sans le préfixe, comme le contrat les écrit (servers: /api).
     *
     * @return list<string>
     */
    private function routesApi(): array
    {
        return $this->routesLaravel(fn (string $uri) => str_starts_with($uri, 'api/') ? substr($uri, 3) : null);
    }

    /**
     * Les routes web décrites dans le contrat (connexion GitHub, lien magique, CSRF, Reverb).
     *
     * @return list<string>
     */
    private function routesWeb(): array
    {
        return $this->routesLaravel(fn (string $uri) => str_starts_with($uri, 'api/') ? null : '/'.ltrim($uri, '/'));
    }

    /**
     * @param  callable(string): ?string  $chemin
     * @return list<string>
     */
    private function routesLaravel(callable $chemin): array
    {
        $routes = [];

        /** @var Route $route */
        foreach (Routes::getRoutes() as $route) {
            $uri = $chemin($route->uri());

            if ($uri === null) {
                continue;
            }

            foreach ($route->methods() as $methode) {
                if ($methode !== 'HEAD') {
                    $routes[] = $methode.' '.$this->normaliser($uri);
                }
            }
        }

        return array_values(array_unique($routes));
    }

    /**
     * Lecture ligne à ligne de la section paths de openapi.yaml (chemins à 2 espaces, méthodes à 4).
     *
     * @return list<string>
     */
    private function routesContrat(): array
    {
        $lignes = file(base_path('../openapi.yaml'), FILE_IGNORE_NEW_LINES) ?: [];
        $routes = [];
        $dansPaths = false;
        $chemin = null;

        foreach ($lignes as $ligne) {
            if ($ligne === 'paths:') {
                $dansPaths = true;

                continue;
            }

            if ($dansPaths && preg_match('/^\S/', $ligne) === 1) {
                break;
            }

            if (preg_match('#^  (/\S*):\s*$#', $ligne, $trouve) === 1) {
                $chemin = $trouve[1];
            } elseif ($chemin !== null && preg_match('/^    (get|post|put|patch|delete):/', $ligne, $trouve) === 1) {
                $routes[] = strtoupper($trouve[1]).' '.$this->normaliser($chemin);
            }
        }

        $this->assertNotEmpty($routes, 'Aucune route lue dans openapi.yaml.');

        return array_values(array_unique($routes));
    }

    /**
     * {appli}, {id} ou {token} : seul l'emplacement du paramètre compte.
     */
    private function normaliser(string $chemin): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', $chemin);
    }
}
