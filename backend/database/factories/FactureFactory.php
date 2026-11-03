<?php

namespace Database\Factories;

use App\Models\Abonnement;
use App\Models\Facture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facture>
 */
class FactureFactory extends Factory
{
    /**
     * Facture à payer d'un mois d'hébergement et d'appli, échéance aujourd'hui.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'abonnement_id' => Abonnement::factory(),
            'client_id' => fn (array $attributs) => Abonnement::query()->findOrFail($attributs['abonnement_id'])->client_id,
            'periode_debut' => today(),
            'periode_fin' => today()->addMonthNoOverflow()->subDay(),
            'echeance_le' => today(),
            'montant_hebergement' => 3500,
            'montant_appli' => 15000,
            'montant_total' => 18500,
            'statut' => 'a_payer',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Facture $facture) => $facture->numeroter());
    }
}
