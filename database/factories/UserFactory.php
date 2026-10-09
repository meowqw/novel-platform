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
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = strtolower(fake()->unique()->bothify('user_##??'));

        return [
            'username' => $username,
            'name' => fake()->name(),
            'email' => strtolower(fake()->unique()->safeEmail()),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password12345'),
            'bio' => null,
            'avatar_path' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
