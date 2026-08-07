<?php

namespace App\Console\Commands;

use App\Mail\NewsletterDigest;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the week's top posts to every active subscriber. Each send is
 * queued individually (NewsletterDigest implements ShouldQueue), so this
 * command just dispatches fast and the queue worker does the sending.
 */
class SendNewsletterDigest extends Command
{
    protected $signature = 'newsletter:send-digest {--limit=5 : How many recent posts to feature}';

    protected $description = 'Queue the newsletter digest email for every active subscriber';

    public function handle(): int
    {
        $posts = Post::query()
            ->published()
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get();

        if ($posts->isEmpty()) {
            $this->warn('No published posts to feature — nothing sent.');

            return self::SUCCESS;
        }

        $count = 0;

        NewsletterSubscriber::active()->each(function (NewsletterSubscriber $subscriber) use ($posts, &$count) {
            Mail::to($subscriber->email)->queue(new NewsletterDigest($subscriber, $posts));
            $count++;
        });

        $this->info("Queued the digest for {$count} subscriber(s).");

        return self::SUCCESS;
    }
}
