<?php

/**
 * tests/Feature/Livewire/Marketing/NewsletterSubscribeTest.php
 *
 * Covers App\Livewire\Marketing\NewsletterSubscribe: the footer signup
 * form — works for guests, validates the email, links a logged-in
 * user's account, and reactivates a previously unsubscribed address.
 */

use App\Livewire\Marketing\NewsletterSubscribe;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('subscribes a guest with just an email', function () {
    Livewire::test(NewsletterSubscribe::class)
        ->set('email', 'reader@example.com')
        ->call('subscribe')
        ->assertHasNoErrors()
        ->assertSet('subscribed', true);

    $subscriber = NewsletterSubscriber::where('email', 'reader@example.com')->firstOrFail();
    expect($subscriber->isActive())->toBeTrue()
        ->and($subscriber->user_id)->toBeNull()
        ->and($subscriber->unsubscribe_token)->not->toBeEmpty();
});

it('links the subscriber to the authenticated account', function () {
    $user = User::factory()->asUser()->create(['email' => 'me@example.com']);

    Livewire::actingAs($user)
        ->test(NewsletterSubscribe::class)
        ->set('email', 'me@example.com')
        ->call('subscribe');

    $subscriber = NewsletterSubscriber::where('email', 'me@example.com')->firstOrFail();
    expect($subscriber->user_id)->toBe($user->id);
});

it('rejects an invalid email', function () {
    Livewire::test(NewsletterSubscribe::class)
        ->set('email', 'not-an-email')
        ->call('subscribe')
        ->assertHasErrors(['email']);

    expect(NewsletterSubscriber::count())->toBe(0);
});

it('reactivates a previously unsubscribed address instead of erroring', function () {
    $existing = NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'back-again@example.com']);

    Livewire::test(NewsletterSubscribe::class)
        ->set('email', 'back-again@example.com')
        ->call('subscribe')
        ->assertHasNoErrors();

    expect($existing->fresh()->isActive())->toBeTrue()
        ->and(NewsletterSubscriber::count())->toBe(1);
});
