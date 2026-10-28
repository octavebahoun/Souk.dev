<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    protected $model = Machine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->slug(2),
            'hote_docker' => null,
            'etat' => 'active',
            'joignable' => true,
        ];
    }

    public function maintenance(): static
    {
        return $this->state(['etat' => 'maintenance']);
    }

    public function injoignable(): static
    {
        return $this->state(['joignable' => false]);
    }
}
