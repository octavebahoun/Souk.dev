<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Discussion;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signalement>
 */
class SignalementFactory extends Factory
{
    protected $model = Signalement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cible' => 'discussion',
            'cible_id' => Discussion::factory(),
            'motif' => 'spam',
            'details' => 'Contenu promotionnel hors sujet.',
            'statut' => 'ouvert',
        ];
    }
}
