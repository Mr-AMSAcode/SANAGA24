<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'age' => fake()->optional(0.8)->numberBetween(16, 80),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
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

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Assign the 'user' role after the model is persisted.
     * This is the default state — call explicitly or rely on DatabaseSeeder.
     */
    public function asUser(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('user');
        });
    }

    /**
     * Assign the 'editor' role.
     * Usage: User::factory()->editor()->create()
     */
    public function editor(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('editor');
        });
    }

    /**
     * Assign the 'admin' role.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('admin');
        });
    }
}
