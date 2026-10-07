<?php

declare(strict_types=1);

namespace App\Soukdev;

final class EnvGenerator
{
    public function __construct(private readonly SecretGenerator $secrets) {}

    /**
     * Écrit le .env d'une copie. Les valeurs qui contiennent un espace ou un
     * caractère spécial sont mises entre guillemets, au format lu par Docker Compose.
     *
     * @param  array<string, string>  $valeursClient
     */
    public function generate(Manifest $manifest, array $valeursClient): string
    {
        $errors = $this->clientErrors($manifest, $valeursClient);

        if ($errors !== []) {
            throw new InvalidClientValuesException($errors);
        }

        $lines = [];

        foreach ($manifest->variables as $variable) {
            $value = match ($variable->source) {
                'client' => $this->clientValue($variable, $valeursClient),
                'plateforme' => $this->secrets->generate(),
                'fixe' => (string) $variable->valeur,
                default => throw new \LogicException('Source inconnue : '.$variable->source),
            };

            if ($value === null) {
                continue;
            }

            $lines[] = $variable->nom.'='.$this->encode($value);
        }

        if ($lines === []) {
            return '';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, string>  $valeursClient
     * @return list<string>
     */
    private function clientErrors(Manifest $manifest, array $valeursClient): array
    {
        $clients = [];

        foreach ($manifest->variables as $variable) {
            if ($variable->source === 'client') {
                $clients[$variable->nom] = $variable;
            }
        }

        $errors = [];

        foreach ($valeursClient as $nom => $valeur) {
            if (! is_string($nom) || ! isset($clients[$nom])) {
                $errors[] = $nom.' n\'est pas une variable à remplir par le client.';

                continue;
            }

            if (! is_string($valeur)) {
                $errors[] = $nom.' doit être une chaîne.';
            }
        }

        foreach ($clients as $variable) {
            $missing = ! array_key_exists($variable->nom, $valeursClient)
                || $valeursClient[$variable->nom] === '';

            if ($variable->requis && $missing) {
                $errors[] = $variable->nom.' est requis.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $valeursClient
     */
    private function clientValue(Variable $variable, array $valeursClient): ?string
    {
        if (! array_key_exists($variable->nom, $valeursClient) || $valeursClient[$variable->nom] === '') {
            return null;
        }

        return $valeursClient[$variable->nom];
    }

    private function encode(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.\/:@+-]*$/', $value) === 1) {
            return $value;
        }

        $escaped = str_replace(
            ['\\', '"', "\n", "\r"],
            ['\\\\', '\\"', '\\n', '\\r'],
            $value,
        );

        return '"'.$escaped.'"';
    }
}
