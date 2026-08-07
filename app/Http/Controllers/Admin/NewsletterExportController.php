<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterExportController extends Controller
{
    /**
     * Stream active subscribers as a CSV — kept out of Livewire since
     * file downloads from a component action aren't this codebase's
     * established pattern; a plain admin-gated route is simplest here.
     */
    public function __invoke(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('admin.panel.view'), 403);

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Email', 'Subscribed At']);

            NewsletterSubscriber::query()
                ->active()
                ->orderBy('subscribed_at')
                ->chunk(500, function ($subscribers) use ($handle) {
                    foreach ($subscribers as $subscriber) {
                        fputcsv($handle, [
                            $subscriber->email,
                            $subscriber->subscribed_at->toDateString(),
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->streamDownload($callback, 'newsletter-subscribers.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
