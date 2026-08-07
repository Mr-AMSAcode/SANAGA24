<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CommentReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Comment $reply,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New reply to your comment on Sanaga24')
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->reply->user->name.' replied to your comment on "'.$this->reply->post->title.'".')
            ->line('"'.Str::limit($this->reply->content, 200).'"')
            ->action('View the conversation', route('posts.show', $this->reply->post).'#comments')
            ->line('Thanks for being part of the discussion!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'comment_reply',
            'reply_id' => $this->reply->id,
            'post_id' => $this->reply->post_id,
            'post_slug' => $this->reply->post->slug,
            'post_title' => $this->reply->post->title,
            'replier_name' => $this->reply->user->name,
            'excerpt' => Str::limit($this->reply->content, 100),
        ];
    }
}
