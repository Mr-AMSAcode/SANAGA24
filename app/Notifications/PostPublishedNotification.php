<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Notification;

/**
 * Confirms to the owning editor that their scheduled post went live —
 * dispatched from App\Console\Commands\PublishScheduledPosts.
 */
class PostPublishedNotification extends Notification
{
    public function __construct(
        public Post $post,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'post_published',
            'post_id' => $this->post->id,
            'post_slug' => $this->post->slug,
            'post_title' => $this->post->title,
        ];
    }
}
