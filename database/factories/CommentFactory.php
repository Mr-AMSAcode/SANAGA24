<?php

// ═══════════════════════════════════════════════════════════
// CommentFactory.php — database/factories/CommentFactory.php
// ═══════════════════════════════════════════════════════════

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Comment> */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asUser(),
            'post_id' => Post::factory()->published(),
            'parent_id' => null,    // top-level by default
            'content' => fake()->paragraph(),
        ];
    }

    /**
     * Create a reply to a given comment.
     * Usage: Comment::factory()->replyTo($comment)->create()
     */
    public function replyTo(\App\Models\Comment $parent): static
    {
        return $this->state([
            'post_id' => $parent->post_id,
            'parent_id' => $parent->id,
        ]);
    }
}
