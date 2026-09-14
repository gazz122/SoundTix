<?php

namespace Database\Factories;

use App\Models\Performer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Performer>
 */
class PerformerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'genre' => fake()->randomElement(['Pop', 'Rock', 'Jazz', 'Dangdut', 'EDM', 'K-Pop', 'Indie', 'Hip Hop', 'R&B']),
            'image_path' => null,
        ];
    }
}
