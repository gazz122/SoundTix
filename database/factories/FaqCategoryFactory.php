<?php

namespace Database\Factories;

use App\Models\FaqCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqCategory>
 */
class FaqCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => FaqCategory::makeSlug($name),
            'icon' => fake()->randomElement(['Ticket', 'ShieldCheck', 'QrCode', 'CreditCard', 'CalendarCheck']),
            'order' => 0,
        ];
    }
}
