<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;

/**
 * Refuse un docker-compose.yml dangereux avant tout lancement.
 * Réutilisable plus tard sur la vérification et la publication d'une appli.
 */
final class VerificateurCompose
{
    public function verifier(string $yaml): void
    {
        $document = $this->document($yaml);

        if (array_key_exists('include', $document)) {
            throw new LancementEchoue('docker-compose.yml utilise include et ne peut pas être vérifié.');
        }

        $services = $document['services'] ?? null;

        if (! is_array($services) || array_is_list($services)) {
            throw new LancementEchoue('docker-compose.yml ne déclare aucun service.');
        }

        $erreurs = [];

        foreach ($services as $nom => $service) {
            $nom = (string) $nom;

            if (! is_array($service) || array_is_list($service)) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            if (array_key_exists('extends', $service)) {
                throw new LancementEchoue("Le service « {$nom} » utilise extends et ne peut pas être vérifié.");
            }

            $this->verifierPrivileged($nom, $service, $erreurs);
            $this->verifierNetworkMode($nom, $service, $erreurs);
            $this->verifierPorts($nom, $service, $erreurs);
            $this->verifierVolumesService($nom, $service, $erreurs);
        }

        $this->verifierVolumesRacine($document['volumes'] ?? null, $erreurs);

        if ($erreurs !== []) {
            throw new LancementEchoue("docker-compose.yml est dangereux :\n".implode("\n", $erreurs));
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierPrivileged(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('privileged', $service) || $service['privileged'] === null) {
            return;
        }

        if ($this->estVrai($service['privileged'])) {
            $erreurs[] = "Le service « {$nom} » est privileged.";

            return;
        }

        if (! $this->estFaux($service['privileged'])) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierNetworkMode(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('network_mode', $service) || $service['network_mode'] === null) {
            return;
        }

        if (! is_string($service['network_mode'])) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        if (strtolower($service['network_mode']) === 'host') {
            $erreurs[] = "Le service « {$nom} » utilise network_mode host.";
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierPorts(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('ports', $service) || $service['ports'] === null || $service['ports'] === '' || $service['ports'] === []) {
            return;
        }

        $ports = $service['ports'];

        if (is_string($ports) || is_int($ports)) {
            $erreurs[] = "Le service « {$nom} » publie des ports.";

            return;
        }

        if (! is_array($ports) || ! array_is_list($ports)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        if ($ports !== []) {
            $erreurs[] = "Le service « {$nom} » publie des ports.";
        }
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  list<string>  $erreurs
     */
    private function verifierVolumesService(string $nom, array $service, array &$erreurs): void
    {
        if (! array_key_exists('volumes', $service) || $service['volumes'] === null) {
            return;
        }

        $volumes = $service['volumes'];

        if (is_string($volumes)) {
            $volumes = [$volumes];
        }

        if (! is_array($volumes) || ! array_is_list($volumes)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        foreach ($volumes as $volume) {
            $source = $this->sourceHote($volume);

            if ($source !== null) {
                $erreurs[] = "Le service « {$nom} » monte un volume de l'hôte « {$source} ».";
            }
        }
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function verifierVolumesRacine(mixed $volumes, array &$erreurs): void
    {
        if ($volumes === null) {
            return;
        }

        if (! is_array($volumes) || array_is_list($volumes)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        foreach ($volumes as $nom => $definition) {
            if ($definition === null) {
                continue;
            }

            if (! is_array($definition) || array_is_list($definition)) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            $options = $definition['driver_opts'] ?? null;

            if ($options === null) {
                continue;
            }

            if (! is_array($options) || array_is_list($options)) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            $type = strtolower((string) ($options['type'] ?? ''));
            $option = strtolower((string) ($options['o'] ?? ''));
            $device = (string) ($options['device'] ?? '');

            if ($type === 'bind' || str_contains($option, 'bind') || $this->estCheminHote($device)) {
                $cible = $device !== '' ? " « {$device} »" : '';
                $erreurs[] = "Le volume « {$nom} » monte un chemin de l'hôte{$cible}.";
            }
        }
    }

    private function sourceHote(mixed $volume): ?string
    {
        if (is_string($volume)) {
            return $this->sourceHoteCourt($volume);
        }

        if (! is_array($volume) || array_is_list($volume)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        $type = isset($volume['type']) ? strtolower((string) $volume['type']) : null;
        $source = isset($volume['source']) ? (string) $volume['source'] : '';

        if ($type === 'bind' || $type === 'npipe') {
            return $source !== '' ? $source : $type;
        }

        if ($this->estCheminHote($source)) {
            return $source;
        }

        $options = $volume['driver_opts'] ?? null;

        if (is_array($options)) {
            $device = (string) ($options['device'] ?? '');
            $option = strtolower((string) ($options['o'] ?? ''));

            if (str_contains($option, 'bind') || $this->estCheminHote($device)) {
                return $device !== '' ? $device : 'bind';
            }
        }

        return null;
    }

    private function sourceHoteCourt(string $spec): ?string
    {
        $spec = trim($spec);

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $spec) === 1) {
            $source = preg_split('/:(?=[^\\\\\\/])/', $spec, 2)[0] ?? $spec;

            return $source !== '' ? $source : $spec;
        }

        $parties = explode(':', $spec);

        if (count($parties) < 2) {
            return null;
        }

        return $this->estCheminHote($parties[0]) ? $parties[0] : null;
    }

    private function estCheminHote(string $source): bool
    {
        if ($source === '') {
            return false;
        }

        return $source === '.'
            || $source === '..'
            || str_starts_with($source, '/')
            || str_starts_with($source, '.')
            || str_starts_with($source, '~')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $source) === 1;
    }

    private function estVrai(mixed $valeur): bool
    {
        if (is_bool($valeur)) {
            return $valeur;
        }

        if (is_int($valeur)) {
            return $valeur === 1;
        }

        return is_string($valeur) && in_array(strtolower($valeur), ['true', 'yes', 'on', '1'], true);
    }

    private function estFaux(mixed $valeur): bool
    {
        if (is_bool($valeur)) {
            return ! $valeur;
        }

        if (is_int($valeur)) {
            return $valeur === 0;
        }

        return is_string($valeur) && in_array(strtolower($valeur), ['false', 'no', 'off', '0'], true);
    }

    /**
     * @return array<mixed>
     */
    private function document(string $yaml): array
    {
        if (str_contains($yaml, "\t")) {
            throw new LancementEchoue('docker-compose.yml utilise des tabulations et ne peut pas être vérifié.');
        }

        if (preg_match('/<<:|(^|[\s\[{,])&[A-Za-z_]|(^|[\s\[{,])\*[A-Za-z_]/', $yaml) === 1) {
            throw new LancementEchoue('docker-compose.yml utilise une ancre YAML et ne peut pas être vérifié.');
        }

        $lignes = [];

        foreach (preg_split('/\R/', $yaml) ?: [] as $brute) {
            $sans = $this->sansCommentaire($brute);

            if (trim($sans) === '' || trim($sans) === '---' || trim($sans) === '...') {
                continue;
            }

            preg_match('/^( *)/', $sans, $espaces);
            $lignes[] = [
                'indent' => strlen($espaces[1]),
                'texte' => ltrim($sans),
            ];
        }

        if ($lignes === []) {
            throw new LancementEchoue('docker-compose.yml est vide ou illisible.');
        }

        $index = 0;
        $document = $this->lireNoeud($lignes, $index);

        if (isset($lignes[$index]) || ! is_array($document) || array_is_list($document)) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        return $document;
    }

    /**
     * @param  list<array{indent: int, texte: string}>  $lignes
     * @return array<mixed>
     */
    private function lireNoeud(array $lignes, int &$index): array
    {
        if (! isset($lignes[$index])) {
            return [];
        }

        if (str_starts_with($lignes[$index]['texte'], '- ') || $lignes[$index]['texte'] === '-') {
            return $this->lireListe($lignes, $index, $lignes[$index]['indent']);
        }

        return $this->lireMap($lignes, $index, $lignes[$index]['indent']);
    }

    /**
     * @param  list<array{indent: int, texte: string}>  $lignes
     * @return array<mixed>
     */
    private function lireMap(array $lignes, int &$index, int $indent): array
    {
        $map = [];

        while (isset($lignes[$index]) && $lignes[$index]['indent'] === $indent && ! $this->estPuce($lignes[$index]['texte'])) {
            $couple = $this->decouperCle($lignes[$index]['texte']);

            if ($couple === null) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            [$cle, $reste] = $couple;
            $this->refuserBlocScalaire($reste);
            $index++;

            if ($reste === '') {
                if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indent) {
                    $map[$cle] = null;
                } else {
                    $map[$cle] = $this->lireNoeud($lignes, $index);
                }

                continue;
            }

            $map[$cle] = $this->scalaire($reste);
        }

        return $map;
    }

    /**
     * @param  list<array{indent: int, texte: string}>  $lignes
     * @return list<mixed>
     */
    private function lireListe(array $lignes, int &$index, int $indent): array
    {
        $liste = [];

        while (isset($lignes[$index]) && $lignes[$index]['indent'] === $indent && $this->estPuce($lignes[$index]['texte'])) {
            $texte = $lignes[$index]['texte'];
            $contenu = $texte === '-' ? '' : ltrim(substr($texte, 1));
            $index++;

            if ($contenu === '') {
                if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indent) {
                    $liste[] = null;
                } else {
                    $liste[] = $this->lireNoeud($lignes, $index);
                }

                continue;
            }

            $couple = $this->decouperCle($contenu);

            if ($couple === null) {
                $liste[] = $this->scalaire($contenu);

                continue;
            }

            [$cle, $reste] = $couple;
            $this->refuserBlocScalaire($reste);
            $map = [];

            if ($reste === '') {
                if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indent) {
                    $map[$cle] = null;
                } else {
                    $map[$cle] = $this->lireNoeud($lignes, $index);
                }
            } else {
                $map[$cle] = $this->scalaire($reste);
            }

            while (isset($lignes[$index]) && $lignes[$index]['indent'] > $indent && ! $this->estPuce($lignes[$index]['texte'])) {
                $suite = $this->decouperCle($lignes[$index]['texte']);

                if ($suite === null) {
                    throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
                }

                [$cleSuite, $resteSuite] = $suite;
                $indentCle = $lignes[$index]['indent'];
                $this->refuserBlocScalaire($resteSuite);
                $index++;

                if ($resteSuite === '') {
                    if (! isset($lignes[$index]) || $lignes[$index]['indent'] <= $indentCle) {
                        $map[$cleSuite] = null;
                    } else {
                        $map[$cleSuite] = $this->lireNoeud($lignes, $index);
                    }
                } else {
                    $map[$cleSuite] = $this->scalaire($resteSuite);
                }
            }

            $liste[] = $map;
        }

        return $liste;
    }

    private function estPuce(string $texte): bool
    {
        return $texte === '-' || str_starts_with($texte, '- ');
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function decouperCle(string $texte): ?array
    {
        if (preg_match('/^([^:#][^:]*):(.*)$/', $texte, $morceaux) !== 1) {
            return null;
        }

        $reste = $morceaux[2];

        if ($reste !== '' && ! preg_match('/^\s/', $reste)) {
            return null;
        }

        return [rtrim($morceaux[1]), trim($reste)];
    }

    private function refuserBlocScalaire(string $reste): void
    {
        if ($reste === '|' || $reste === '>' || str_starts_with($reste, '|') || str_starts_with($reste, '>')) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }
    }

    private function scalaire(string $valeur): mixed
    {
        if ($valeur === '') {
            return '';
        }

        $premier = $valeur[0];

        if ($premier === '[' || $premier === '{') {
            return $this->lireFlux($valeur);
        }

        if (
            (strlen($valeur) >= 2 && $premier === '"' && str_ends_with($valeur, '"'))
            || (strlen($valeur) >= 2 && $premier === "'" && str_ends_with($valeur, "'"))
        ) {
            return substr($valeur, 1, -1);
        }

        $bas = strtolower($valeur);

        if (in_array($bas, ['true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($bas, ['false', 'no', 'off'], true)) {
            return false;
        }

        if (in_array($bas, ['null', '~'], true)) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $valeur) === 1) {
            return (int) $valeur;
        }

        return $valeur;
    }

    private function lireFlux(string $texte): mixed
    {
        $texte = trim($texte);
        $ouvrant = $texte[0] ?? '';
        $fermant = $ouvrant === '[' ? ']' : '}';

        if ($ouvrant !== '[' && $ouvrant !== '{') {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        $fin = $this->finFlux($texte, 0);

        if ($fin !== strlen($texte) - 1 || $texte[$fin] !== $fermant) {
            throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
        }

        $morceaux = $this->separerFlux(substr($texte, 1, $fin - 1));

        if ($ouvrant === '[') {
            return array_map(fn (string $morceau): mixed => $this->scalaire(trim($morceau)), $morceaux);
        }

        $map = [];

        foreach ($morceaux as $morceau) {
            $morceau = trim($morceau);

            if (! str_contains($morceau, ':')) {
                throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
            }

            [$cle, $valeur] = explode(':', $morceau, 2);
            $cle = trim($cle);

            if (
                (str_starts_with($cle, '"') && str_ends_with($cle, '"'))
                || (str_starts_with($cle, "'") && str_ends_with($cle, "'"))
            ) {
                $cle = substr($cle, 1, -1);
            }

            $map[$cle] = $this->scalaire(trim($valeur));
        }

        return $map;
    }

    private function finFlux(string $texte, int $debut): int
    {
        $fermant = $texte[$debut] === '[' ? ']' : '}';
        $profondeur = 0;
        $guillemet = null;
        $longueur = strlen($texte);

        for ($i = $debut; $i < $longueur; $i++) {
            $caractere = $texte[$i];

            if ($guillemet !== null) {
                if ($caractere === '\\' && $guillemet === '"') {
                    $i++;

                    continue;
                }

                if ($caractere === $guillemet) {
                    $guillemet = null;
                }

                continue;
            }

            if ($caractere === '"' || $caractere === "'") {
                $guillemet = $caractere;

                continue;
            }

            if ($caractere === '[' || $caractere === '{') {
                $profondeur++;

                continue;
            }

            if (($caractere === ']' || $caractere === '}') && --$profondeur === 0 && $caractere === $fermant) {
                return $i;
            }
        }

        throw new LancementEchoue('docker-compose.yml ne peut pas être vérifié.');
    }

    /**
     * @return list<string>
     */
    private function separerFlux(string $corps): array
    {
        if (trim($corps) === '') {
            return [];
        }

        $morceaux = [];
        $debut = 0;
        $profondeur = 0;
        $guillemet = null;
        $longueur = strlen($corps);

        for ($i = 0; $i < $longueur; $i++) {
            $caractere = $corps[$i];

            if ($guillemet !== null) {
                if ($caractere === '\\' && $guillemet === '"') {
                    $i++;

                    continue;
                }

                if ($caractere === $guillemet) {
                    $guillemet = null;
                }

                continue;
            }

            if ($caractere === '"' || $caractere === "'") {
                $guillemet = $caractere;

                continue;
            }

            if ($caractere === '[' || $caractere === '{') {
                $profondeur++;

                continue;
            }

            if ($caractere === ']' || $caractere === '}') {
                $profondeur--;

                continue;
            }

            if ($caractere === ',' && $profondeur === 0) {
                $morceaux[] = substr($corps, $debut, $i - $debut);
                $debut = $i + 1;
            }
        }

        $morceaux[] = substr($corps, $debut);

        return $morceaux;
    }

    private function sansCommentaire(string $ligne): string
    {
        $guillemet = null;
        $longueur = strlen($ligne);

        for ($i = 0; $i < $longueur; $i++) {
            $caractere = $ligne[$i];

            if ($guillemet !== null) {
                if ($caractere === '\\' && $guillemet === '"') {
                    $i++;

                    continue;
                }

                if ($caractere === $guillemet) {
                    $guillemet = null;
                }

                continue;
            }

            if ($caractere === '"' || $caractere === "'") {
                $guillemet = $caractere;

                continue;
            }

            if ($caractere === '#' && ($i === 0 || $ligne[$i - 1] === ' ')) {
                return rtrim(substr($ligne, 0, $i));
            }
        }

        return $ligne;
    }
}
