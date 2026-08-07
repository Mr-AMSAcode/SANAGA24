<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostRevision>
 */
class PostRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'editor_id' => User::factory()->editor(),
            'title' => fake()->sentence(6),
            'content' => fake()->paragraphs(5, true),
            'section' => fake()->randomElement(\App\Enums\PostSection::values()),
            'created_at' => now(),
        ];
    }
}
