<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
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
            'name' => ucwords($name),
            'slug' => str($name)->slug(),
            'price_cents' => fake()->numberBetween(0, 10000),
            'rate_limit_per_minute' => 60,
            'level' => fake()->numberBetween(0, 2),
            'features' => null,
        ];
    }
}
