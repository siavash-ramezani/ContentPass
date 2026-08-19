<?php

namespace Database\Factories;

use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);
        $type = fake()->randomElement(['article', 'video']);

        return [
            'title' => rtrim($title, '.'),
            'slug' => str($title)->slug(),
            'body' => $type === 'article' ? fake()->paragraphs(5, true) : null,
            'video_url' => $type === 'video' ? 'https://videos.contentpass.test/'.fake()->uuid() : null,
            'type' => $type,
            'required_plan_level' => fake()->numberBetween(0, 2),
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
