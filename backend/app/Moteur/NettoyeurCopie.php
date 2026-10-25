<?php

namespace App\Moteur;

use App\Models\Deploiement;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Arrête vraiment une copie : docker compose down (conteneurs, volumes dont
 * la base intégrée, images construites), puis suppression du dossier cloné.
 * Un seul chemin pour DELETE, l'échec d'un lancement et l'expiration.
 */
class NettoyeurCopie
{
    public function __construct(
        private LanceurCompose $lanceur,
        private DossierCopie $dossier,
    ) {}

    /**
     * @return bool Vrai si la copie est arrêtée (ou l'était déjà).
     */
    public function arreter(Deploiement $deploiement): bool
    {
        $deploiement->refresh();

        if ($deploiement->etat === 'arrete') {
            return true;
        }

        if (! $this->liberer($deploiement->copie, $deploiement->repertoire, $deploiement->id)) {
            $deploiement->changerEtat('echec', erreur: 'L\'arrêt de la copie a échoué, il sera retenté.');

            return false;
        }

        $deploiement->changerEtat('arrete');

        return true;
    }

    /**
     * Libère les conteneurs et le dossier sans toucher à l'état (utile après un échec).
     */
    public function liberer(?string $copie, ?string $repertoire, ?int $deploiementId = null): bool
    {
        if (is_string($copie) && $copie !== '' && is_string($repertoire) && $repertoire !== '') {
            if (! $this->descendre($repertoire, $copie, $deploiementId)) {
                return false;
            }
        }

        $chemin = $repertoire ?: $this->dossierParDefaut($deploiementId);

        if (! $this->dossier->supprimer($chemin)) {
            Log::warning('moteur: dossier de copie non supprimé', ['deploiement' => $deploiementId, 'repertoire' => $chemin]);
        }

        return true;
    }

    private function descendre(string $repertoire, string $copie, ?int $deploiementId): bool
    {
        $tentatives = max(1, (int) (function_exists('config') ? config('moteur.arret_tentatives', 2) : 2));

        for ($essai = 1; $essai <= $tentatives; $essai++) {
            try {
                $this->lanceur->arreter($repertoire, $copie);

                return true;
            } catch (Throwable $exception) {
                Log::error('moteur: arrêt de la copie impossible', [
                    'deploiement' => $deploiementId,
                    'copie' => $copie,
                    'essai' => $essai,
                    'erreur' => $exception->getMessage(),
                ]);
            }
        }

        return false;
    }

    private function dossierParDefaut(?int $deploiementId): ?string
    {
        $racine = $this->dossier->racine();

        return $deploiementId === null || $racine === null ? null : $racine.DIRECTORY_SEPARATOR.'d'.$deploiementId;
    }
}
