<?php

declare(strict_types=1);

namespace App\Soukdev;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\JsonPointer;
use Opis\JsonSchema\Validator;
use RuntimeException;
use stdClass;

final class SchemaValidator
{
    private stdClass $schema;

    private Validator $validator;

    public function __construct(private readonly string $schemaPath)
    {
        if (! is_file($this->schemaPath)) {
            throw new RuntimeException('Schéma introuvable : '.$this->schemaPath);
        }

        $decoded = json_decode((string) file_get_contents($this->schemaPath));

        if (! $decoded instanceof stdClass) {
            throw new RuntimeException('Schéma illisible : '.$this->schemaPath);
        }

        $this->schema = $decoded;
        $this->validator = new Validator;
        $this->validator->setMaxErrors(100);
        $this->validator->setStopAtFirstError(false);
    }

    public function validate(mixed $document): Manifest
    {
        $data = $this->decode($document);
        $result = $this->validator->validate($data, $this->schema);

        if (! $result->isValid()) {
            $errors = $this->messages($result->error(), $data);

            throw new InvalidManifestException(
                $errors === [] ? ['Le fichier ne respecte pas soukdev.schema.json.'] : $errors,
            );
        }

        $duplicates = $this->duplicateNames($data);

        if ($duplicates !== []) {
            throw new InvalidManifestException($duplicates);
        }

        return $this->manifest($data);
    }

    private function decode(mixed $document): object
    {
        if (is_string($document)) {
            try {
                $document = json_decode($document, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new InvalidManifestException(['Le fichier n\'est pas du JSON valide.']);
            }
        } elseif (is_array($document)) {
            $document = json_decode((string) json_encode($document));
        }

        if (! is_object($document)) {
            throw new InvalidManifestException(['/ : doit être un objet']);
        }

        return $document;
    }

    /**
     * @return list<string>
     */
    private function messages(ValidationError $error, object $document): array
    {
        $messages = [];

        foreach ($this->leaves($error) as $leaf) {
            $message = $this->message($leaf, $document);

            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @return list<ValidationError>
     */
    private function leaves(ValidationError $error): array
    {
        if ($error->subErrors() === []) {
            return [$error];
        }

        $leaves = [];

        foreach ($error->subErrors() as $subError) {
            array_push($leaves, ...$this->leaves($subError));
        }

        return $leaves;
    }

    private function message(ValidationError $error, object $document): ?string
    {
        $path = JsonPointer::pathToString($error->data()->fullPath());

        if ($path === '') {
            $path = '/';
        }

        $args = $error->args();

        if ($error->keyword() === 'additionalProperties') {
            $unknown = $this->unknownProperties($path, $args['properties'] ?? [], $document);

            if ($unknown === []) {
                return null;
            }

            $label = count($unknown) > 1 ? 'propriétés inconnues' : 'propriété inconnue';

            return $path.' : '.$label.' : '.implode(', ', $unknown);
        }

        $detail = match ($error->keyword()) {
            'required' => 'il manque '.implode(', ', $args['missing'] ?? []),
            'const' => 'doit être '.$this->literal($args['const'] ?? null),
            'enum' => $path === '/backend'
                ? 'doit être « propre » ou « integre »'
                : 'valeur non autorisée',
            'pattern' => $this->patternMessage($path),
            'type' => 'doit être '.$this->typeLabel($args['expected'] ?? null),
            'minimum' => 'doit être supérieur ou égal à '.($args['min'] ?? ''),
            'maximum' => 'doit être inférieur ou égal à '.($args['max'] ?? ''),
            'minLength' => $this->lengthMessage('au moins', $args['min'] ?? null),
            'maxLength' => $this->lengthMessage('au plus', $args['max'] ?? null),
            'minItems' => $this->countMessage('au moins', $args['min'] ?? null),
            'maxItems' => $this->countMessage('au plus', $args['max'] ?? null),
            'not' => 'migrations est interdit lorsque backend n\'est pas « integre »',
            default => 'valeur invalide',
        };

        return $path.' : '.$detail;
    }

    /**
     * @param  list<mixed>  $listed
     * @return list<string>
     */
    private function unknownProperties(string $path, array $listed, object $document): array
    {
        $declared = $this->declaredProperties($path, $document);

        if ($declared === null) {
            return array_values(array_filter($listed, is_string(...)));
        }

        return array_values(array_filter(
            $listed,
            fn (mixed $name): bool => is_string($name) && ! in_array($name, $declared, true),
        ));
    }

    /**
     * Propriétés déclarées à cet endroit du schéma.
     * opis signale aussi les propriétés connues quand une propriété voisine est invalide.
     *
     * @return list<string>|null
     */
    private function declaredProperties(string $path, object $document): ?array
    {
        if ($path === '/') {
            return array_keys((array) $this->schema->properties);
        }

        if ($path === '/service_web') {
            return array_keys((array) $this->schema->properties->service_web->properties);
        }

        if (preg_match('#^/variables/(\d+)$#', $path, $matches) !== 1) {
            return null;
        }

        $variable = $document->variables[(int) $matches[1]] ?? null;
        $source = is_object($variable) ? ($variable->source ?? null) : null;

        return match ($source) {
            'client' => ['nom', 'source', 'libelle', 'requis'],
            'plateforme' => ['nom', 'source'],
            'fixe' => ['nom', 'source', 'valeur'],
            default => array_keys((array) $this->schema->{'$defs'}->variable->properties),
        };
    }

    private function patternMessage(string $path): string
    {
        if ($path === '/service_web/nom') {
            return 'le nom du conteneur doit commencer par une lettre ou un chiffre';
        }

        if ($path === '/migrations') {
            return 'le chemin doit être relatif, sans .. ni barre oblique au début';
        }

        if (preg_match('#^/variables/\d+/nom$#', $path) === 1) {
            return 'le nom doit commencer par une majuscule et ne contenir que des majuscules, des chiffres et _';
        }

        return 'format invalide';
    }

    private function typeLabel(mixed $expected): string
    {
        return match ($expected) {
            'integer' => 'un entier',
            'string' => 'une chaîne',
            'boolean' => 'un booléen',
            'object' => 'un objet',
            'array' => 'un tableau',
            'number' => 'un nombre',
            default => 'du bon type',
        };
    }

    private function literal(mixed $value): string
    {
        if (is_string($value)) {
            return '« '.$value.' »';
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function lengthMessage(string $bound, mixed $limit): string
    {
        $count = is_numeric($limit) ? (int) $limit : 0;
        $word = $count === 1 ? 'caractère' : 'caractères';

        return 'doit contenir '.$bound.' '.$count.' '.$word;
    }

    private function countMessage(string $bound, mixed $limit): string
    {
        $count = is_numeric($limit) ? (int) $limit : 0;
        $word = $count === 1 ? 'élément' : 'éléments';

        return 'doit contenir '.$bound.' '.$count.' '.$word;
    }

    /**
     * @return list<string>
     */
    private function duplicateNames(object $document): array
    {
        $errors = [];
        $seen = [];

        foreach ($document->variables as $index => $variable) {
            $name = $variable->nom;

            if (isset($seen[$name])) {
                $errors[] = '/variables/'.$index.'/nom : '.$name.' est déjà utilisé';
            }

            $seen[$name] = true;
        }

        return $errors;
    }

    private function manifest(object $document): Manifest
    {
        $variables = [];

        foreach ($document->variables as $variable) {
            $variables[] = new Variable(
                nom: $variable->nom,
                source: $variable->source,
                libelle: $variable->libelle ?? null,
                requis: property_exists($variable, 'requis') ? (bool) $variable->requis : null,
                valeur: $variable->valeur ?? null,
            );
        }

        return new Manifest(
            version: (int) $document->version,
            serviceWeb: $document->service_web->nom,
            port: (int) $document->service_web->port,
            backend: $document->backend,
            migrations: $document->migrations ?? null,
            variables: $variables,
        );
    }
}
