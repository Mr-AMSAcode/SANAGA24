<?php

/**
 * tests/Feature/Livewire/Admin/NewsletterSubscriberListTest.php
 *
 * Covers App\Livewire\Admin\NewsletterSubscriberList: access control,
 * search, and that unsubscribed addresses never show up as "active".
 */

use App\Livewire\Admin\NewsletterSubscriberList;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('access control', function () {
    it('denies regular users', function () {
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)->test(NewsletterSubscriberList::class)->assertForbidden();
    });

    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(NewsletterSubscriberList::class)->assertOk();
    });
});

describe('subscribers()', function () {
    it('only counts and lists active subscribers', function () {
        $admin = User::factory()->admin()->create();
        $active = NewsletterSubscriber::factory()->create();
        NewsletterSubscriber::factory()->unsubscribed()->create();

        $component = Livewire::actingAs($admin)->test(NewsletterSubscriberList::class);

        expect($component->instance()->activeCount())->toBe(1)
            ->and($component->instance()->subscribers()->pluck('id')->toArray())->toBe([$active->id]);
    });

    it('searches by email', function () {
        $admin = User::factory()->admin()->create();
        $match = NewsletterSubscriber::factory()->create(['email' => 'zendaya@example.com']);
        NewsletterSubscriber::factory()->create(['email' => 'someoneelse@example.com']);

        $subscribers = Livewire::actingAs($admin)
            ->test(NewsletterSubscriberList::class)
            ->set('search', 'zendaya')
            ->instance()->subscribers();

        expect($subscribers->pluck('id')->toArray())->toBe([$match->id]);
    });
});
