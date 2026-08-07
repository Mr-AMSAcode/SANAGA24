<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Video> */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory()->published(),
            'type' => 'embed',
            'url' => 'https://www.youtube.com/embed/'.fake()->regexify('[A-Za-z0-9_-]{11}'),
            'provider' => 'youtube',
            'title' => fake()->sentence(4),
            'file_size' => null,
            'created_at' => now(),
        ];
    }

    public function embed(string $provider = 'youtube'): static
    {
        $url = $provider === 'vimeo'
            ? 'https://player.vimeo.com/video/'.fake()->numberBetween(100000000, 999999999)
            : 'https://www.youtube.com/embed/'.fake()->regexify('[A-Za-z0-9_-]{11}');

        return $this->state([
            'type' => 'embed',
            'provider' => $provider,
            'url' => $url,
            'file_size' => null,
        ]);
    }

    public function upload(): static
    {
        return $this->state([
            'type' => 'upload',
            'provider' => null,
            'url' => '/storage/posts/videos/'.fake()->uuid().'.mp4',
            'file_size' => fake()->numberBetween(500_000, 40_000_000),
        ]);
    }
}
