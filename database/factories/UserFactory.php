<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'google_id' => fake()->unique()->numerify('#####################'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'profile_picture' => fake()->imageUrl(200, 200, 'people'),
        ];
    }
}
