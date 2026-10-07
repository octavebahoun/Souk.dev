<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Soukdev\InvalidManifestException;
use App\Soukdev\SchemaValidator;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    public function test_il_accepte_lexemple_du_brief(): void
    {
        $manifest = $this->validator()->validate($this->exemple());

        $this->assertSame(1, $manifest->version);
        $this->assertSame('front', $manifest->serviceWeb);
        $this->assertSame(3000, $manifest->port);
        $this->assertSame('propre', $manifest->backend);
        $this->assertNull($manifest->migrations);
        $this->assertCount(3, $manifest->variables);
        $this->assertSame('APP_NOM', $manifest->variables[0]->nom);
        $this->assertSame('client', $manifest->variables[0]->source);
        $this->assertSame('Nom de votre structure', $manifest->variables[0]->libelle);
        $this->assertTrue($manifest->variables[0]->requis);
        $this->assertSame('plateforme', $manifest->variables[1]->source);
        $this->assertSame('FCFA', $manifest->variables[2]->valeur);
    }

    public function test_il_accepte_une_chaine_json(): void
    {
        $manifest = $this->validator()->validate((string) json_encode($this->exemple()));

        $this->assertSame('front', $manifest->serviceWeb);
    }

    public function test_il_accepte_un_backend_integre_avec_ses_migrations(): void
    {
        $document = $this->exemple();
        $document['backend'] = 'integre';
        $document['migrations'] = 'database/migrations';

        $manifest = $this->validator()->validate($document);

        $this->assertSame('integre', $manifest->backend);
        $this->assertSame('database/migrations', $manifest->migrations);
    }

    public function test_il_refuse_un_backend_integre_sans_migrations(): void
    {
        $document = $this->exemple();
        $document['backend'] = 'integre';

        $this->assertInvalid($document, [
            '/ : il manque migrations',
        ]);
    }

    public function test_il_refuse_les_migrations_quand_le_backend_est_propre(): void
    {
        $document = $this->exemple();
        $document['migrations'] = 'database/migrations';

        $this->assertInvalid($document, [
            '/ : migrations est interdit lorsque backend n\'est pas « integre »',
        ]);
    }

    public function test_il_refuse_une_variable_client_incomplete(): void
    {
        $document = $this->exemple();
        $document['variables'][0] = ['nom' => 'APP_NOM', 'source' => 'client'];

        $this->assertInvalid($document, [
            '/variables/0 : il manque libelle, requis',
        ]);
    }

    public function test_il_refuse_une_valeur_sur_une_variable_plateforme(): void
    {
        $document = $this->exemple();
        $document['variables'][1]['valeur'] = 'secret';

        $this->assertInvalid($document, [
            '/variables/1 : propriété inconnue : valeur',
        ]);
    }

    public function test_il_refuse_une_variable_fixe_sans_valeur(): void
    {
        $document = $this->exemple();
        unset($document['variables'][2]['valeur']);

        $this->assertInvalid($document, [
            '/variables/2 : il manque valeur',
        ]);
    }

    public function test_il_refuse_un_port_hors_limite_et_une_propriete_inconnue(): void
    {
        $document = $this->exemple();
        $document['service_web']['port'] = 0;
        $document['foo'] = true;

        $this->assertInvalid($document, [
            '/service_web/port : doit être supérieur ou égal à 1',
            '/ : propriété inconnue : foo',
        ]);
    }

    public function test_il_refuse_une_propriete_inconnue_du_service_web(): void
    {
        $document = $this->exemple();
        $document['service_web']['image'] = 'nginx';

        $this->assertInvalid($document, [
            '/service_web : propriété inconnue : image',
        ]);
    }

    public function test_il_refuse_un_nom_de_variable_invalide(): void
    {
        $document = $this->exemple();
        $document['variables'][1]['nom'] = 'db_password';

        $this->assertInvalid($document, [
            '/variables/1/nom : le nom doit commencer par une majuscule et ne contenir que des majuscules, des chiffres et _',
        ]);
    }

    public function test_il_refuse_un_chemin_de_migration_qui_sort_du_depot(): void
    {
        $document = $this->exemple();
        $document['backend'] = 'integre';
        $document['migrations'] = '../secret';

        $this->assertInvalid($document, [
            '/migrations : le chemin doit être relatif, sans .. ni barre oblique au début',
        ]);
    }

    public function test_il_refuse_deux_variables_du_meme_nom(): void
    {
        $document = $this->exemple();
        $document['variables'][2]['nom'] = 'APP_NOM';

        $this->assertInvalid($document, [
            '/variables/2/nom : APP_NOM est déjà utilisé',
        ]);
    }

    public function test_il_refuse_un_json_invalide(): void
    {
        $this->assertInvalid('{', [
            'Le fichier n\'est pas du JSON valide.',
        ]);
    }

    private function assertInvalid(mixed $document, array $errors): void
    {
        try {
            $this->validator()->validate($document);
            $this->fail('Le document aurait dû être refusé.');
        } catch (InvalidManifestException $exception) {
            $this->assertSame($errors, $exception->errors);
        }
    }

    private function validator(): SchemaValidator
    {
        return new SchemaValidator(dirname(__DIR__, 3).'/soukdev.schema.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function exemple(): array
    {
        return [
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom de votre structure', 'requis' => true],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
                ['nom' => 'DEVISE', 'source' => 'fixe', 'valeur' => 'FCFA'],
            ],
        ];
    }
}
