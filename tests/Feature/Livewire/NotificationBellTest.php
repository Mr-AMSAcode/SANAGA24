<?php

/**
 * tests/Feature/Livewire/NotificationBellTest.php
 *
 * Covers App\Livewire\NotificationBell: unread count, marking one
 * notification (or all) as read, and that it never leaks another
 * user's notifications.
 */

use App\Livewire\NotificationBell;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostPublishedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('counts only this user\'s unread notifications', function () {
    $editor = User::factory()->editor()->create();
    $otherEditor = User::factory()->editor()->create();
    $post = Post::factory()->for($editor, 'editor')->create();

    $editor->notify(new PostPublishedNotification($post));
    $editor->notify(new PostPublishedNotification($post));
    $otherEditor->notify(new PostPublishedNotification($post));

    $count = Livewire::actingAs($editor)->test(NotificationBell::class)->instance()->unreadCount();

    expect($count)->toBe(2);
});

it('marks a single notification as read', function () {
    $editor = User::factory()->editor()->create();
    $post = Post::factory()->for($editor, 'editor')->create();
    $editor->notify(new PostPublishedNotification($post));
    $notificationId = $editor->notifications()->first()->id;

    Livewire::actingAs($editor)
        ->test(NotificationBell::class)
        ->call('markAsRead', $notificationId);

    expect($editor->unreadNotifications()->count())->toBe(0);
});

it('marks every notification as read at once', function () {
    $editor = User::factory()->editor()->create();
    $post = Post::factory()->for($editor, 'editor')->create();
    $editor->notify(new PostPublishedNotification($post));
    $editor->notify(new PostPublishedNotification($post));

    Livewire::actingAs($editor)
        ->test(NotificationBell::class)
        ->call('markAllAsRead');

    expect($editor->unreadNotifications()->count())->toBe(0);
});
