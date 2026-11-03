<?php

namespace App\Jobs;

use App\Moteur\Commande;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;

/**
 * Ce qu'une analyse de sécurité interrompue (travail tué au délai, worker redémarré) laisse
 * derrière elle : conteneurs étiquetés soukdev.analyse et clones « a… » de plus d'une heure.
 * Sur la file « analyse » : seul ce worker parle à Docker pour les analyses.
 */
class NettoyerAnalyses implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('analyse');
    }

    public function handle(): void
    {
        Commande::isolee()->timeout(120)->run(['docker', 'container', 'prune', '--force', '--filter', 'label=soukdev.analyse=1', '--filter', 'until=1h']);

        $parent = (string) config('moteur.copies');
        foreach (File::directories($parent) as $dossier) {
            if (preg_match('/^a[0-9a-f]{12}$/', basename($dossier)) === 1 && ! is_link($dossier) && filemtime($dossier) < time() - 3600) {
                File::deleteDirectory($dossier);
            }
        }
    }
}
