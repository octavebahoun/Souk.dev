<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\LanceurCompose;
use App\Moteur\SauvegardeVolumes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sauvegarde à chaud d'une copie en ligne (chaque nuit, ou à la demande de son client), sur le
 * moteur : la base du backend intégré par pg_dump, les autres volumes en archive.
 */
class SauvegarderCopie implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $deploiementId, public string $type = 'quotidienne')
    {
        $this->onQueue('moteur');
    }

    public function uniqueId(): string
    {
        return (string) $this->deploiementId;
    }

    public function handle(SauvegardeVolumes $sauvegardes, LanceurCompose $lanceur): void
    {
        $copie = Deploiement::query()->find($this->deploiementId);
        if ($copie === null || $copie->etat !== 'en_ligne' || $copie->copie === null || ! is_dir((string) $copie->repertoire)) {
            return;
        }

        $integre = is_file($copie->repertoire.DIRECTORY_SEPARATOR.'docker-compose.backend.yml');

        try {
            $sauvegardes->sauvegarderAChaud(
                (string) $copie->copie,
                $this->type,
                $integre ? $lanceur->argumentsCompose((string) $copie->repertoire, (string) $copie->copie) : null,
                $integre ? (string) $copie->repertoire : null,
                $copie->cibleMachine(),
            );
        } catch (Throwable $erreur) {
            Log::warning('sauvegarde: copie non sauvegardée', ['copie' => $copie->id, 'erreur' => class_basename($erreur)]);

            throw $erreur;
        }

        // « Mes copies » montre la nouvelle sauvegarde, et son lien d'export, sans rechargement.
        $copie->diffuser();
    }
}
