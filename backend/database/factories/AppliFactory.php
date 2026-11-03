<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appli;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appli>
 */
class AppliFactory extends Factory
{
    protected $model = Appli::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->github(),
            'nom' => ucfirst(fake()->words(2, true)),
            'description' => fake()->sentence(12),
            'categorie' => 'commerce',
            'prix' => 15000,
            'type_prix' => 'mensuel',
            'taille' => 'petite',
            'demo_url' => 'https://demo.example.com',
            'depot_url' => 'https://github.com/acme/'.fake()->unique()->slug(2),
            'stack' => ['laravel'],
            'captures' => [['url' => 'https://example.com/capture.png']],
            'backend' => 'propre',
            'variables_client' => [],
        ];
    }

    public function gratuite(): static
    {
        return $this->state(fn (array $attributes) => ['prix' => 0]);
    }
}
