<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mission>
 */
class MissionFactory extends Factory
{
    protected $model = Mission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->client(),
            'auteur_id' => User::factory()->github(),
            'app_id' => null,
            'message' => fake()->paragraph(),
            'budget' => 150000,
            'delai' => null,
            'statut' => 'en_cours',
        ];
    }
}
