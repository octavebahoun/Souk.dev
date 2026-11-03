<?php

namespace Database\Factories;

use App\Models\Abonnement;
use App\Models\Deploiement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Abonnement>
 */
class AbonnementFactory extends Factory
{
    /**
     * Abonnement actif d'une copie client en ligne : client, dev et appli suivent la copie.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $copie = fn (array $attributs): Deploiement => Deploiement::query()->with('appli')->findOrFail($attributs['deploiement_id']);

        return [
            'deploiement_id' => Deploiement::factory(),
            'client_id' => fn (array $attributs) => $copie($attributs)->user_id,
            'dev_id' => fn (array $attributs) => $copie($attributs)->appli->user_id,
            'appli_id' => fn (array $attributs) => $copie($attributs)->appli_id,
            'formule' => 'petite',
            'montant_hebergement' => 3500,
            'prix_appli' => 15000,
            'type_prix' => 'mensuel',
            'statut' => 'actif',
            'periode_debut' => today()->subMonth(),
            'prochaine_echeance' => today()->addDays(10),
        ];
    }
}
