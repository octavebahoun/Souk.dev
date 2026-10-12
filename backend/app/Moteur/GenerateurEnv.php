<?php

namespace App\Moteur;

use App\Moteur\Exceptions\VariablesManquantes;

final class GenerateurEnv
{
    /**
     * @param  array<string, mixed>  $manifeste
     * @param  array<string, string>  $variablesClient
     * @return array<string, string>
     */
    public function generer(array $manifeste, array $variablesClient = []): array
    {
        $paires = [];

        foreach ($manifeste['variables'] as $variable) {
            $nom = $variable['nom'];

            $valeur = match ($variable['source']) {
                'fixe' => $variable['valeur'],
                'plateforme' => $this->secret(),
                'client' => $this->valeurClient($variable, $variablesClient),
            };

            if ($valeur === null) {
                continue;
            }

            $paires[$nom] = $valeur;
        }

        return $paires;
    }

    /**
     * @param  array<string, string>  $paires
     */
    public function ecrire(string $chemin, array $paires): void
    {
        $lignes = [];

        foreach ($paires as $nom => $valeur) {
            if (str_contains($valeur, "\n") || str_contains($valeur, "\r")) {
                throw new VariablesManquantes("La valeur de {$nom} ne peut pas contenir de retour à la ligne.");
            }

            $lignes[] = $nom.'='.$this->encoder($valeur);
        }

        file_put_contents($chemin, implode("\n", $lignes)."\n");
    }

    /**
     * @param  array<string, mixed>  $variable
     * @param  array<string, string>  $variablesClient
     */
    private function valeurClient(array $variable, array $variablesClient): ?string
    {
        $nom = $variable['nom'];

        if (array_key_exists($nom, $variablesClient)) {
            return $variablesClient[$nom];
        }

        if ($variable['requis'] === true) {
            throw new VariablesManquantes("Variable client obligatoire manquante : {$nom} ({$variable['libelle']}).");
        }

        return null;
    }

    private function secret(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function encoder(string $valeur): string
    {
        if ($valeur === '' || preg_match('/[\s#"\'\\\\$]/', $valeur) === 1) {
            return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $valeur).'"';
        }

        return $valeur;
    }
}
