<?php

namespace App\Jobs;

use App\Models\Appli;
use App\Moteur\CloneurGithub;
use App\Securite\AnalyseurDepot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dépendances aux failles connues (Trivy, conteneur jetable), après la publication ou à la
 * demande de l'auteur : badge vert « Security Checked » ou orange « Problèmes détectés ».
 * Le jeton écarte le résultat d'une analyse dépassée par une plus récente.
 */
class AnalyserDependances implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [300];

    public int $timeout = 1200;

    public function __construct(public int $appliId, public string $jeton)
    {
        $this->onQueue('analyse');
    }

    public function handle(CloneurGithub $cloneur, AnalyseurDepot $analyseur): void
    {
        $appli = Appli::query()->find($this->appliId);

        if ($appli === null || $appli->securite_jeton !== $this->jeton) {
            return;
        }

        $repertoire = null;

        try {
            $repertoire = $cloneur->cloner((string) $appli->depot_url, 'a'.bin2hex(random_bytes(6)));
            $problemes = $analyseur->dependances($repertoire);
        } finally {
            if (is_string($repertoire)) {
                File::deleteDirectory($repertoire);
            }
        }

        $appli->refresh();

        if ($appli->securite_jeton !== $this->jeton) {
            return;
        }

        $appli->forceFill([
            'securite_statut' => $problemes === [] ? 'verifie' : 'problemes',
            'securite_problemes' => $problemes,
            'securite_analyse_le' => now(),
        ])->save();
    }

    /** Une analyse qui échoue deux fois laisse l'appli « en cours » : l'auteur peut la relancer. */
    public function failed(Throwable $exception): void
    {
        Log::warning('securite: analyse des dépendances impossible', ['appli' => $this->appliId, 'erreur' => $exception->getMessage()]);
    }
}
