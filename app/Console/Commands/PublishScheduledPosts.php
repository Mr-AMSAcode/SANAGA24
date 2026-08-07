<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Notifications\PostPublishedNotification;
use Illuminate\Console\Command;

/**
 * Flips every Scheduled post whose active_period_start has arrived over
 * to Published. Intended to run every minute (see routes/console.php).
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish scheduled posts whose go-live time has arrived';

    public function handle(): int
    {
        $due = Post::query()->dueForPublishing()->with(['postStatus', 'editor'])->get();

        foreach ($due as $post) {
            $post->update(['status' => PostStatus::Published]);
            // active_period_start is left untouched — it already holds the
            // intended (and now actual) go-live time.
            $post->postStatus?->update(['is_archived' => false]);
            $post->editor?->notify(new PostPublishedNotification($post));

            $this->info("Published \"{$post->title}\" (#{$post->id}).");
        }

        if ($due->isEmpty()) {
            $this->line('No scheduled posts were due.');
        }

        return self::SUCCESS;
    }
}
