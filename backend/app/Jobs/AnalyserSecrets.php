<?php

namespace App\Jobs;

use App\Moteur\CloneurGithub;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Securite\AnalyseurDepot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Recherche de secrets avant publication (gitleaks, conteneur jetable), sur sa propre file
 * « secrets ». Le résultat attend l'API dans le cache (VerificationSecrets) ; une demande dont
 * la requête a déjà abandonné n'est pas analysée.
 */
class AnalyserSecrets implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Clonage (120 s), conteneur (60 s), copie (120 s), gitleaks (300 s) et retrait (30 s). */
    public int $timeout = 700;

    public function __construct(public string $depotUrl, public string $cle, public ?int $expireLe = null)
    {
        $this->onQueue('secrets');
    }

    public function handle(CloneurGithub $cloneur, AnalyseurDepot $analyseur): void
    {
        if ($this->expireLe !== null && time() > $this->expireLe) {
            return;
        }

        $repertoire = null;

        try {
            $repertoire = $cloneur->cloner($this->depotUrl, 'a'.bin2hex(random_bytes(6)));
            $resultat = ['statut' => 'termine', 'secrets' => $analyseur->secrets($repertoire)];
        } catch (DepotInvalide|LancementEchoue $exception) {
            $resultat = ['statut' => 'erreur', 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            report($exception);
            $resultat = ['statut' => 'erreur', 'message' => 'L\'analyse de sécurité a échoué.'];
        } finally {
            if (is_string($repertoire)) {
                File::deleteDirectory($repertoire);
            }
        }

        Cache::put($this->cle, $resultat, now()->addMinutes(10));
    }
}
