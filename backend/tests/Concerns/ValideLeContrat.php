<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/**
 * Valide le corps JSON d'une réponse de l'API contre openapi.yaml (schéma de la route, de la
 * méthode et du statut) : le front est typé depuis le contrat, un écart y casserait un écran.
 * OpenAPI 3.1 décrit ses schémas en JSON Schema 2020-12, que lit opis/json-schema.
 */
trait ValideLeContrat
{
    private const CONTRAT = 'https://souk.dev/openapi.json';

    private static ?Validator $validateurContrat = null;

    /** @var array<string, mixed>|null */
    private static ?array $contrat = null;

    /**
     * Chaque appel JSON à /api passe par le contrat, dès qu'il y documente ce statut avec un schéma.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $reponse = parent::json($method, $uri, $data, $headers, $options);

        if (str_starts_with(ltrim((string) parse_url($uri, PHP_URL_PATH), '/'), 'api/') && self::documente($method, $uri, $reponse->getStatusCode())) {
            $this->assertConformeAuContrat($reponse, $method, $uri);
        }

        return $reponse;
    }

    private static function documente(string $methode, string $chemin, int $statut): bool
    {
        $contrat = self::contrat();
        $gabarit = self::gabarit($contrat, $chemin);

        return $gabarit !== null && isset($contrat['paths'][$gabarit][strtolower($methode)]['responses'][(string) $statut]['content']['application/json']['schema']);
    }

    protected function assertConformeAuContrat(TestResponse $reponse, string $methode, string $chemin): void
    {
        $contrat = self::contrat();
        $gabarit = self::gabarit($contrat, $chemin);
        $this->assertNotNull($gabarit, "Route absente du contrat : {$chemin}");

        $statut = (string) $reponse->getStatusCode();
        $operation = $contrat['paths'][$gabarit][strtolower($methode)] ?? null;
        $this->assertIsArray($operation, "Méthode absente du contrat : {$methode} {$gabarit}");
        $this->assertArrayHasKey($statut, $operation['responses'], "Statut {$statut} absent du contrat pour {$methode} {$gabarit}");

        if (! isset($operation['responses'][$statut]['content']['application/json']['schema'])) {
            return;
        }

        // Pointeur JSON dans un fragment d'URI : « { » et « } » s'y encodent (RFC 6901, § 6).
        $segments = ['paths', self::echapper($gabarit), strtolower($methode), 'responses', $statut, 'content', 'application~1json', 'schema'];
        $pointeur = '#/'.implode('/', array_map('rawurlencode', $segments));
        $resultat = self::validateur()->validate(json_decode((string) $reponse->getContent()), (object) ['$ref' => self::CONTRAT.$pointeur]);
        $erreurs = $resultat->isValid() ? [] : (new ErrorFormatter)->formatFlat($resultat->error());

        $this->assertSame([], $erreurs, "Réponse de {$methode} {$chemin} ({$statut}) hors contrat");
    }

    /**
     * @return array<string, mixed>
     */
    private static function contrat(): array
    {
        return self::$contrat ??= Yaml::parseFile(base_path('../openapi.yaml'));
    }

    private static function validateur(): Validator
    {
        if (self::$validateurContrat === null) {
            self::$validateurContrat = new Validator;
            self::$validateurContrat->setMaxErrors(5);
            self::$validateurContrat->resolver()->registerRaw((string) json_encode(self::contrat()), self::CONTRAT);
        }

        return self::$validateurContrat;
    }

    /** « /api/factures/12 » → « /factures/{facture} », le chemin tel que le contrat l'écrit. */
    private static function gabarit(array $contrat, string $chemin): ?string
    {
        $chemin = '/'.ltrim((string) preg_replace('#^/?api/#', '', (string) parse_url($chemin, PHP_URL_PATH)), '/');

        foreach (array_keys($contrat['paths']) as $gabarit) {
            $motif = '#^'.preg_replace('#\\\\\{[^/]+\\\\\}#', '[^/]+', preg_quote($gabarit, '#')).'$#';

            if (preg_match($motif, $chemin) === 1) {
                return $gabarit;
            }
        }

        return null;
    }

    private static function echapper(string $gabarit): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $gabarit);
    }
}
