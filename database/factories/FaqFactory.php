<?php

namespace Database\Factories;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => FaqCategory::factory(),
            'question' => fake()->sentence(6),
            'answer' => fake()->paragraph(),
            'is_published' => true,
            'order' => 0,
        ];
    }
}
