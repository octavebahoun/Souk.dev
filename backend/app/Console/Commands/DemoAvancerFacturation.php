<?php

namespace App\Console\Commands;

use App\Models\Abonnement;
use App\Paiement\CycleFacturation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Développement et tests de bout en bout : fait comme si des jours avaient passé pour une copie
 * client (échéances, factures, suspension), puis lance le passage quotidien de facturation.
 * Sert à montrer pour de vrai un impayé, sa suspension puis sa réactivation. Jamais en production.
 */
class DemoAvancerFacturation extends Command
{
    protected $signature = 'demo:avancer-facturation
        {deploiement : Identifiant de la copie client}
        {--jours=39 : Nombre de jours à faire passer}';

    protected $description = 'Fait passer des jours sur la facturation d\'une copie, puis lance le cycle (développement seulement)';

    public function handle(CycleFacturation $cycle): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Réservé au développement : la facturation réelle ne se déplace pas dans le temps.');

            return self::FAILURE;
        }

        $abonnement = Abonnement::query()->where('deploiement_id', (int) $this->argument('deploiement'))->first();

        if ($abonnement === null) {
            $this->error('Aucun abonnement pour cette copie.');

            return self::FAILURE;
        }

        $jours = max(0, (int) $this->option('jours'));

        DB::transaction(function () use ($abonnement, $jours): void {
            $reculer = fn ($date) => $date?->copy()->subDays($jours);

            $abonnement->forceFill([
                'periode_debut' => $reculer($abonnement->periode_debut),
                'prochaine_echeance' => $reculer($abonnement->prochaine_echeance),
                'suspendu_le' => $reculer($abonnement->suspendu_le),
                'avertissement_arret_le' => $reculer($abonnement->avertissement_arret_le),
                'created_at' => $reculer($abonnement->created_at),
            ])->save();

            // Du plus ancien au plus récent : deux périodes ne se croisent jamais en route.
            foreach ($abonnement->factures()->orderBy('periode_debut')->get() as $facture) {
                $facture->forceFill([
                    'periode_debut' => $reculer($facture->periode_debut),
                    'periode_fin' => $reculer($facture->periode_fin),
                    'echeance_le' => $reculer($facture->echeance_le),
                    'rappel_envoye_le' => $reculer($facture->rappel_envoye_le),
                    'retard_signale_le' => $reculer($facture->retard_signale_le),
                    'created_at' => $reculer($facture->created_at),
                ])->save();
            }
        });

        $resultat = $cycle->executer();
        $this->info("{$jours} jours passés pour la copie {$this->argument('deploiement')}.");
        $this->table(array_keys($resultat), [$resultat]);

        return self::SUCCESS;
    }
}
