<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Discussion;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discussion_id' => Discussion::factory(),
            'user_id' => User::factory(),
            'texte' => fake()->paragraph(),
        ];
    }
}
