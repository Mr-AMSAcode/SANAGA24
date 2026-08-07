<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Archives every Published post whose active_period_end has passed.
 * Intended to run every minute (see routes/console.php).
 */
class ArchiveExpiredPosts extends Command
{
    protected $signature = 'posts:archive-expired';

    protected $description = 'Archive published posts whose active period has ended';

    public function handle(): int
    {
        $due = Post::query()->dueForArchiving()->with('postStatus')->get();

        foreach ($due as $post) {
            $post->update(['status' => PostStatus::Archived]);
            $post->postStatus?->update(['is_archived' => true]);

            $this->info("Archived \"{$post->title}\" (#{$post->id}).");
        }

        if ($due->isEmpty()) {
            $this->line('No published posts had expired.');
        }

        return self::SUCCESS;
    }
}
