<?php

namespace App\Moteur;

use App\Moteur\Exceptions\ManifestInvalide;
use JsonException;

final class ValidateurSoukdev
{
    /**
     * @return array<string, mixed>
     */
    public function validerFichier(string $chemin): array
    {
        if (! is_file($chemin)) {
            throw new ManifestInvalide('soukdev.json est absent à la racine du dépôt.');
        }

        $brut = file_get_contents($chemin);

        if ($brut === false || trim($brut) === '') {
            throw new ManifestInvalide('soukdev.json est vide ou illisible.');
        }

        try {
            $donnees = json_decode($brut, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ManifestInvalide('soukdev.json n\'est pas un JSON valide.');
        }

        if (! is_array($donnees) || array_is_list($donnees)) {
            throw new ManifestInvalide('soukdev.json doit être un objet.');
        }

        return $this->valider($donnees);
    }

    /**
     * Valide un manifeste déjà décodé, selon soukdev.schema.json.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    public function valider(array $donnees): array
    {
        $erreurs = [];

        foreach (array_keys($donnees) as $cle) {
            if (! in_array($cle, ['version', 'service_web', 'backend', 'variables', 'migrations'], true)) {
                $erreurs[] = "Propriété inconnue : {$cle}.";
            }
        }

        foreach (['version', 'service_web', 'backend', 'variables'] as $requise) {
            if (! array_key_exists($requise, $donnees)) {
                $erreurs[] = "Champ obligatoire manquant : {$requise}.";
            }
        }

        if (array_key_exists('version', $donnees) && $donnees['version'] !== 1) {
            $erreurs[] = 'version doit valoir 1.';
        }

        if (array_key_exists('backend', $donnees)) {
            if (! in_array($donnees['backend'], ['propre', 'integre'], true)) {
                $erreurs[] = 'backend doit valoir propre ou integre.';
            } elseif ($donnees['backend'] === 'integre' && ! array_key_exists('migrations', $donnees)) {
                $erreurs[] = 'migrations est obligatoire quand backend vaut integre.';
            } elseif ($donnees['backend'] === 'propre' && array_key_exists('migrations', $donnees)) {
                $erreurs[] = 'migrations doit être absent quand backend vaut propre.';
            }
        }

        if (array_key_exists('migrations', $donnees)) {
            $this->verifierMigrations($donnees['migrations'], $erreurs);
        }

        if (array_key_exists('service_web', $donnees)) {
            $this->verifierServiceWeb($donnees['service_web'], $erreurs);
        }

        if (array_key_exists('variables', $donnees)) {
            $this->verifierVariables($donnees['variables'], $erreurs);
        }

        if (($donnees['backend'] ?? null) === 'integre' && is_array($donnees['variables'] ?? null)) {
            $this->verifierVariablesIntegrees($donnees['variables'], $erreurs);
        }

        if ($erreurs !== []) {
            throw new ManifestInvalide(implode("\n", $erreurs));
        }

        return $donnees;
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function verifierServiceWeb(mixed $service, array &$erreurs): void
    {
        if (! is_array($service) || array_is_list($service)) {
            $erreurs[] = 'service_web doit être un objet.';

            return;
        }

        foreach (array_keys($service) as $cle) {
            if (! in_array($cle, ['nom', 'port'], true)) {
                $erreurs[] = "service_web : propriété inconnue {$cle}.";
            }
        }

        if (! array_key_exists('nom', $service) || ! array_key_exists('port', $service)) {
            $erreurs[] = 'service_web doit contenir nom et port.';

            return;
        }

        if (! is_string($service['nom']) || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,62}$/', $service['nom']) !== 1) {
            $erreurs[] = 'service_web.nom est invalide.';
        }

        if (! is_int($service['port']) || $service['port'] < 1 || $service['port'] > 65535) {
            $erreurs[] = 'service_web.port doit être un entier entre 1 et 65535.';
        }
    }

    /**
     * @param  list<mixed>  $variables
     * @param  list<string>  $erreurs
     */
    private function verifierVariablesIntegrees(array $variables, array &$erreurs): void
    {
        foreach ($variables as $variable) {
            if (! is_array($variable)) {
                continue;
            }

            $nom = $variable['nom'] ?? null;

            if ($nom === 'DB_PASSWORD') {
                $erreurs[] = 'DB_PASSWORD est absent quand backend vaut integre : la plateforme injecte SOUKDEV_URL et SOUKDEV_CLE.';
            }

            if ($nom === 'SOUKDEV_URL' || $nom === 'SOUKDEV_CLE') {
                $erreurs[] = "{$nom} est réservé au backend intégré.";
            }
        }
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function verifierMigrations(mixed $migrations, array &$erreurs): void
    {
        if (! is_string($migrations) || strlen($migrations) < 1 || strlen($migrations) > 200) {
            $erreurs[] = 'migrations doit être un chemin relatif de 200 caractères ou moins.';

            return;
        }

        if (str_starts_with($migrations, '/') || str_contains($migrations, '..') || preg_match('/^[a-zA-Z0-9_\\.\\/-]+$/', $migrations) !== 1) {
            $erreurs[] = 'migrations doit être un chemin relatif, sans .. ni slash initial.';
        }
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function verifierVariables(mixed $variables, array &$erreurs): void
    {
        if (! is_array($variables) || ! array_is_list($variables)) {
            $erreurs[] = 'variables doit être un tableau.';

            return;
        }

        if (count($variables) > 50) {
            $erreurs[] = 'variables accepte 50 entrées au maximum.';
        }

        $noms = [];

        foreach ($variables as $index => $variable) {
            $this->verifierVariable($variable, $index, $erreurs);

            if (is_array($variable) && isset($variable['nom']) && is_string($variable['nom'])) {
                if (in_array($variable['nom'], $noms, true)) {
                    $erreurs[] = "Variable en double : {$variable['nom']}.";
                }
                $noms[] = $variable['nom'];
            }
        }
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function verifierVariable(mixed $variable, int $index, array &$erreurs): void
    {
        $prefixe = "variables[{$index}]";

        if (! is_array($variable) || array_is_list($variable)) {
            $erreurs[] = "{$prefixe} doit être un objet.";

            return;
        }

        if (! isset($variable['nom'], $variable['source'])) {
            $erreurs[] = "{$prefixe} doit contenir nom et source.";

            return;
        }

        if (! is_string($variable['nom']) || preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $variable['nom']) !== 1) {
            $erreurs[] = "{$prefixe}.nom est invalide.";
        }

        $source = $variable['source'];

        if (! in_array($source, ['client', 'plateforme', 'fixe'], true)) {
            $erreurs[] = "{$prefixe}.source doit valoir client, plateforme ou fixe.";

            return;
        }

        $autorisees = match ($source) {
            'client' => ['nom', 'source', 'libelle', 'requis'],
            'plateforme' => ['nom', 'source'],
            'fixe' => ['nom', 'source', 'valeur'],
        };

        foreach (array_keys($variable) as $cle) {
            if (! in_array($cle, $autorisees, true)) {
                $erreurs[] = "{$prefixe} : propriété {$cle} interdite pour source {$source}.";
            }
        }

        if ($source === 'client') {
            if (! array_key_exists('libelle', $variable) || ! array_key_exists('requis', $variable)) {
                $erreurs[] = "{$prefixe} (client) doit contenir libelle et requis.";
            } else {
                if (! is_string($variable['libelle']) || $variable['libelle'] === '' || strlen($variable['libelle']) > 100) {
                    $erreurs[] = "{$prefixe}.libelle est invalide.";
                }
                if (! is_bool($variable['requis'])) {
                    $erreurs[] = "{$prefixe}.requis doit être un booléen.";
                }
            }
        }

        if ($source === 'fixe') {
            if (! array_key_exists('valeur', $variable)) {
                $erreurs[] = "{$prefixe} (fixe) doit contenir valeur.";
            } elseif (! is_string($variable['valeur']) || strlen($variable['valeur']) > 500) {
                $erreurs[] = "{$prefixe}.valeur est invalide.";
            }
        }
    }
}
