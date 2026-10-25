<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'profil' => 'dev',
            'github_lie' => false,
            'est_admin' => false,
            'disponible' => false,
        ];
    }

    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'profil' => 'client',
            'github_lie' => false,
        ]);
    }

    /**
     * Un dev qui a lié son GitHub (obligatoire pour publier ou proposer un correctif).
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'profil' => 'dev',
            'github_lie' => true,
            'username' => fake()->unique()->regexify('[a-z][a-z0-9]{5,12}'),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'est_admin' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
