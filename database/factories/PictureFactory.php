<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Picture> */
class PictureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory()->published(),
            'url' => 'https://picsum.photos/seed/'.fake()->word().'/800/600',
            'alt_text' => fake()->sentence(4),
            'is_featured' => false,
            'created_at' => now(),
        ];
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
