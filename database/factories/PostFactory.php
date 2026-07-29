<?php

namespace Database\Factories;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 *
 * Usage:
 *   Post::factory()->create()                        → draft post
 *   Post::factory()->published()->create()           → published post
 *   Post::factory()->published()->for($editor)->create()
 *   Post::factory()->published()->withStats()->create()
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'editor_id' => User::factory()->editor(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'content' => fake()->paragraphs(5, true),
            'section' => fake()->randomElement(PostSection::values()),
            'status' => PostStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => PostStatus::Published]);
    }

    public function archived(): static
    {
        return $this->state(['status' => PostStatus::Archived]);
    }

    public function inSection(PostSection $section): static
    {
        return $this->state(['section' => $section]);
    }

    /**
     * Also create the 1:1 PostStats record.
     * Always use this in tests that check counts.
     */
    public function withStats(): static
    {
        return $this->has(\Database\Factories\PostStatsFactory::new(), 'stats');
    }

    /**
     * Also create the 1:1 PostStatus record.
     */
    public function withStatusRecord(): static
    {
        return $this->has(\Database\Factories\PostStatusFactory::new(), 'postStatus');
    }
}
