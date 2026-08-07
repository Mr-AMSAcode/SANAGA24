<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class NewsletterDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Post>  $posts  The posts to feature in this digest.
     */
    public function __construct(
        public NewsletterSubscriber $subscriber,
        public Collection $posts,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'This week on Sanaga24',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.newsletter-digest',
            with: [
                'posts' => $this->posts,
                'unsubscribeUrl' => URL::route('newsletter.unsubscribe', $this->subscriber->unsubscribe_token),
            ],
        );
    }
}
