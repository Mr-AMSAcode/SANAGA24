<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostStats;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostStats>
 */
class PostStatsFactory extends Factory
{
    protected $model = PostStats::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'view_count' => fake()->numberBetween(0, 10000),
            'like_count' => fake()->numberBetween(0, 500),
            'comment_count' => fake()->numberBetween(0, 200),
            'updated_at' => now(),
        ];
    }

    public function zeroed(): static
    {
        return $this->state([
            'view_count' => 0,
            'like_count' => 0,
            'comment_count' => 0,
        ]);
    }
}
