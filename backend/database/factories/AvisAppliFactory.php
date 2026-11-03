<?php

namespace Database\Factories;

use App\Models\Appli;
use App\Models\AvisAppli;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvisAppli>
 */
class AvisAppliFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appli_id' => Appli::factory(),
            'user_id' => User::factory(),
            'note' => fake()->numberBetween(1, 5),
            'commentaire' => fake()->optional()->sentence(),
        ];
    }
}
