<?php

declare(strict_types=1);

$forbidden = ['GPL', 'AGPL', 'LGPL'];
$unknownLicenses = ['UNKNOWN', 'UNLICENSED'];

$json = stream_get_contents(STDIN);

if ($json === false || trim($json) === '') {
    fwrite(STDERR, "Entrée vide : fournir la sortie de « composer licenses --format=json » sur l'entrée standard.\n");
    exit(2);
}

try {
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, 'JSON invalide : '.$exception->getMessage()."\n");
    exit(2);
}

$packages = $data['dependencies'] ?? $data;
$violations = [];
$warnings = [];

foreach ($packages as $name => $package) {
    $licenses = $package['license'] ?? null;

    if (! is_array($licenses) || $licenses === []) {
        $warnings[] = $name.' : aucune licence déclarée';

        continue;
    }

    $options = [];

    foreach ($licenses as $license) {
        if (! is_string($license)) {
            continue;
        }

        foreach (preg_split('/\s+OR\s+/i', $license) ?: [] as $option) {
            if (trim($option) !== '') {
                $options[] = trim($option);
            }
        }
    }

    if ($options === []) {
        $warnings[] = $name.' : aucune licence déclarée';

        continue;
    }

    $knownOptions = array_filter(
        $options,
        static fn (string $option): bool => ! in_array(strtoupper($option), $unknownLicenses, true)
    );

    if ($knownOptions === []) {
        $warnings[] = $name.' : '.implode(', ', array_unique($options));

        continue;
    }

    if (! has_allowed_license($options, $forbidden)) {
        $violations[] = $name.' : '.implode(', ', array_unique($options));
    }
}

if ($warnings !== []) {
    fwrite(STDERR, "Avertissement : licence absente ou inconnue, à vérifier manuellement :\n");

    foreach ($warnings as $warning) {
        fwrite(STDERR, ' - '.$warning."\n");
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Licences interdites (GPL, AGPL, LGPL) :\n");

    foreach ($violations as $violation) {
        fwrite(STDERR, ' - '.$violation."\n");
    }

    exit(1);
}

fwrite(STDOUT, "Licences : aucune licence interdite (GPL, AGPL, LGPL).\n");

function has_allowed_license(array $options, array $forbidden): bool
{
    foreach ($options as $option) {
        $isForbidden = false;

        foreach ($forbidden as $needle) {
            if (stripos($option, $needle) !== false) {
                $isForbidden = true;

                break;
            }
        }

        if (! $isForbidden) {
            return true;
        }
    }

    return false;
}
