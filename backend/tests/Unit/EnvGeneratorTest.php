<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Soukdev\EnvGenerator;
use App\Soukdev\InvalidClientValuesException;
use App\Soukdev\Manifest;
use App\Soukdev\RandomSecretGenerator;
use App\Soukdev\SecretGenerator;
use App\Soukdev\Variable;
use PHPUnit\Framework\TestCase;

class EnvGeneratorTest extends TestCase
{
    public function test_il_ecrit_les_trois_sources(): void
    {
        $env = $this->generator(['plateforme-secret'])->generate($this->manifest(), [
            'APP_NOM' => 'Pharmacie',
        ]);

        $this->assertSame(
            "APP_NOM=Pharmacie\nDB_PASSWORD=plateforme-secret\nDEVISE=FCFA\n",
            $env,
        );
    }

    public function test_il_omet_une_variable_client_facultative_absente(): void
    {
        $manifest = new Manifest(1, 'front', 3000, 'propre', null, [
            new Variable('APP_NOM', 'client', 'Nom', true),
            new Variable('SLOGAN', 'client', 'Slogan', false),
        ]);

        $env = $this->generator([])->generate($manifest, [
            'APP_NOM' => 'Pharmacie',
        ]);

        $this->assertSame("APP_NOM=Pharmacie\n", $env);
    }

    public function test_il_cite_une_valeur_qui_contient_un_espace_ou_un_guillemet(): void
    {
        $env = $this->generator(['secret'])->generate($this->manifest(), [
            'APP_NOM' => 'Pharmacie d\'Abomey "centre"',
        ]);

        $this->assertStringStartsWith("APP_NOM=\"Pharmacie d'Abomey \\\"centre\\\"\"\n", $env);
    }

    public function test_il_refuse_une_variable_client_requise_manquante(): void
    {
        try {
            $this->generator(['secret'])->generate($this->manifest(), []);
            $this->fail('La génération aurait dû échouer.');
        } catch (InvalidClientValuesException $exception) {
            $this->assertSame(['APP_NOM est requis.'], $exception->errors);
        }
    }

    public function test_il_refuse_une_valeur_vide_pour_une_variable_requise(): void
    {
        try {
            $this->generator([])->generate($this->manifest(), ['APP_NOM' => '']);
            $this->fail('La génération aurait dû échouer.');
        } catch (InvalidClientValuesException $exception) {
            $this->assertSame(['APP_NOM est requis.'], $exception->errors);
        }
    }

    public function test_il_refuse_une_cle_qui_nest_pas_une_variable_client(): void
    {
        try {
            $this->generator([])->generate($this->manifest(), [
                'APP_NOM' => 'Pharmacie',
                'DB_PASSWORD' => 'choisi-par-le-client',
                'INCONNUE' => 'x',
            ]);
            $this->fail('La génération aurait dû échouer.');
        } catch (InvalidClientValuesException $exception) {
            $this->assertSame([
                'DB_PASSWORD n\'est pas une variable à remplir par le client.',
                'INCONNUE n\'est pas une variable à remplir par le client.',
            ], $exception->errors);
        }
    }

    public function test_un_secret_plateforme_fait_32_caracteres_aleatoires(): void
    {
        $env = (new EnvGenerator(new RandomSecretGenerator))->generate($this->manifest(), [
            'APP_NOM' => 'Pharmacie',
        ]);

        $this->assertMatchesRegularExpression('/^DB_PASSWORD=[A-Za-z0-9]{32}$/m', $env);
    }

    private function generator(array $secrets): EnvGenerator
    {
        return new EnvGenerator(new class($secrets) implements SecretGenerator
        {
            /** @param  list<string>  $secrets */
            public function __construct(private array $secrets) {}

            public function generate(): string
            {
                $secret = array_shift($this->secrets);

                if (! is_string($secret)) {
                    throw new \RuntimeException('Plus de secret de test.');
                }

                return $secret;
            }
        });
    }

    private function manifest(): Manifest
    {
        return new Manifest(1, 'front', 3000, 'propre', null, [
            new Variable('APP_NOM', 'client', 'Nom de votre structure', true),
            new Variable('DB_PASSWORD', 'plateforme'),
            new Variable('DEVISE', 'fixe', valeur: 'FCFA'),
        ]);
    }
}
