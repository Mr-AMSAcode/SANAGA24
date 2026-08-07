<?php

/**
 * tests/Feature/Http/NewsletterTest.php
 *
 * Covers the plain-HTTP side of the newsletter feature: the one-click
 * unsubscribe link and the admin CSV export.
 */

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('unsubscribe', function () {
    it('marks the subscriber unsubscribed via the token link', function () {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))->assertOk();

        expect($subscriber->fresh()->isActive())->toBeFalse();
    });

    it('is silent about an invalid token, never erroring', function () {
        $this->get(route('newsletter.unsubscribe', 'not-a-real-token'))->assertOk();
    });

    it('is idempotent — unsubscribing twice does not error', function () {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))->assertOk();
        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))->assertOk();

        expect($subscriber->fresh()->isActive())->toBeFalse();
    });
});

describe('CSV export', function () {
    it('denies non-admins', function () {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user)->get(route('admin.newsletter.export'))->assertForbidden();
    });

    it('streams active subscribers as CSV, excluding unsubscribed ones', function () {
        $admin = User::factory()->admin()->create();
        NewsletterSubscriber::factory()->create(['email' => 'active@example.com']);
        NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.newsletter.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect($response->streamedContent())
            ->toContain('active@example.com')
            ->not->toContain('gone@example.com');
    });
});
