<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deploiement>
 */
class DeploiementFactory extends Factory
{
    protected $model = Deploiement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'appli_id' => Appli::factory(),
            'type' => 'client',
            'taille' => 'petite',
            'etat' => 'en_ligne',
            'url' => null,
            'variables' => [],
        ];
    }
}
