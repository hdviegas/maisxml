<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Default plain password tested through the codebase: {@see md5}.
     *
     * @var string
     */
    public const LEGACY_PASSWORD = 'password';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $digits = fake()->numerify('##############');

        return [
            'social' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'login' => fake()->unique()->userName(),
            'senha' => md5(self::LEGACY_PASSWORD),
            'cnpj' => $digits,
            'tipo' => 1,
        ];
    }
}
