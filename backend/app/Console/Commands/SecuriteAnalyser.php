<?php

namespace App\Console\Commands;

use App\Models\Appli;
use Illuminate\Console\Command;

/**
 * Relance l'analyse des dépendances : les applis jamais analysées (publiées avant la sécurité
 * avant publication, ou restées « en cours » plus d'une heure), ou toutes avec --toutes (la base
 * des failles grandit chaque jour : un badge vert d'il y a un mois n'en dit plus assez).
 */
class SecuriteAnalyser extends Command
{
    protected $signature = 'securite:analyser {--toutes : Toutes les applis publiées}';

    protected $description = 'Met en file l\'analyse de sécurité des applis à (re)vérifier';

    public function handle(): int
    {
        $applis = Appli::query()
            ->when(! $this->option('toutes'), fn ($requete) => $requete->where('securite_statut', 'en_cours')
                ->where(fn ($ancienne) => $ancienne->whereNull('securite_demande_le')->orWhere('securite_demande_le', '<=', now()->subHour())))
            ->orderBy('id')
            ->get();

        $applis->each->demanderAnalyseSecurite();
        $this->info($applis->count().' analyse(s) mise(s) en file.');

        return self::SUCCESS;
    }
}
