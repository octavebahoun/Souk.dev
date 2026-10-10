<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Canal;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discussion>
 */
class DiscussionFactory extends Factory
{
    protected $model = Discussion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'canal_id' => Canal::factory(),
            'app_id' => null,
            'titre' => fake()->sentence(4),
            'texte' => fake()->paragraph(),
            'depot_url' => null,
            'demo_url' => null,
            'etiquettes' => [],
            'bug' => null,
            'resolue' => false,
        ];
    }
}
