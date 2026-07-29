<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostStatus>
 */
class PostStatusFactory extends Factory
{
    protected $model = PostStatus::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'active_period_start' => now()->subDays(rand(1, 30)),
            'active_period_end' => null,
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state([
            'is_archived' => true,
            'active_period_end' => now()->subDays(1),
        ]);
    }
}
