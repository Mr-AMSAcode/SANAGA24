<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Like> */
class LikeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'target_type' => 'post',
            'target_id' => Post::factory()->published(),
            'created_at' => now(),
        ];
    }

    public function forComment(\App\Models\Comment $comment): static
    {
        return $this->state([
            'target_type' => 'comment',
            'target_id' => $comment->id,
        ]);
    }

    public function forPost(Post $post): static
    {
        return $this->state([
            'target_type' => 'post',
            'target_id' => $post->id,
        ]);
    }
}
