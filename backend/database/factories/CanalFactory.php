<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Canal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Canal>
 */
class CanalFactory extends Factory
{
    protected $model = Canal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = fake()->unique()->words(2, true);

        return [
            'slug' => str($nom)->slug()->toString(),
            'nom' => $nom,
            'description' => fake()->optional()->sentence(),
            'pays' => null,
        ];
    }
}
