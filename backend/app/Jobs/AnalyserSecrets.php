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
 * Recherche de secrets avant publication (gitleaks, conteneur jetable), sur la file « analyse »
 * du moteur. Le résultat attend l'API dans le cache (VerificationSecrets) quelques minutes.
 */
class AnalyserSecrets implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public string $depotUrl, public string $cle)
    {
        $this->onQueue('analyse');
    }

    public function handle(CloneurGithub $cloneur, AnalyseurDepot $analyseur): void
    {
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
