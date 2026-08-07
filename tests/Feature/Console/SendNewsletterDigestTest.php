<?php

/**
 * tests/Feature/Console/SendNewsletterDigestTest.php
 *
 * Covers the newsletter:send-digest command: it queues one email per
 * active subscriber (never to unsubscribed addresses), and does
 * nothing when there's no published content to feature.
 */

use App\Console\Commands\SendNewsletterDigest;
use App\Mail\NewsletterDigest;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('queues the digest to every active subscriber only', function () {
    Mail::fake();
    Post::factory()->published()->count(3)->create();
    $active = NewsletterSubscriber::factory()->count(2)->create();
    $unsubscribed = NewsletterSubscriber::factory()->unsubscribed()->create();

    $this->artisan(SendNewsletterDigest::class)->assertSuccessful();

    Mail::assertQueuedCount(2);
    foreach ($active as $subscriber) {
        Mail::assertQueued(NewsletterDigest::class, fn ($mail) => $mail->hasTo($subscriber->email));
    }
    Mail::assertNotQueued(NewsletterDigest::class, fn ($mail) => $mail->hasTo($unsubscribed->email));
});

it('sends nothing when there are no published posts', function () {
    Mail::fake();
    NewsletterSubscriber::factory()->count(3)->create();

    $this->artisan(SendNewsletterDigest::class)->assertSuccessful();

    Mail::assertNothingQueued();
});

it('respects the --limit option for how many posts to feature', function () {
    Mail::fake();
    Post::factory()->published()->count(10)->create();
    NewsletterSubscriber::factory()->create();

    $this->artisan(SendNewsletterDigest::class, ['--limit' => 2])->assertSuccessful();

    Mail::assertQueued(NewsletterDigest::class, fn ($mail) => $mail->posts->count() === 2);
});
