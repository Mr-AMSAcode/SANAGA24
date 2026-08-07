<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Response;

class NewsletterUnsubscribeController extends Controller
{
    /**
     * One-click unsubscribe — no login required. The unguessable token
     * in the URL is the only credential; this is the standard pattern
     * every mail client and provider expects for list-unsubscribe.
     */
    public function __invoke(string $token): Response
    {
        NewsletterSubscriber::where('unsubscribe_token', $token)
            ->whereNull('unsubscribed_at')
            ->first()
            ?->update(['unsubscribed_at' => now()]);

        return response()->view('newsletter.unsubscribed');
    }
}
