<?php

/**
 * tests/Feature/Livewire/Posts/LikeButtonTest.php
 *
 * Covers App\Livewire\Posts\LikeButton: the reusable polymorphic
 * like/unlike control, mounted against either a Post or a Comment.
 */

use App\Events\PostLikeCountUpdated;
use App\Livewire\Posts\LikeButton;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('mount()', function () {
    it('resolves the morph alias and initial like state for a post', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        Like::factory()->forPost($post)->for($user, 'user')->create();
        Like::factory()->forPost($post)->create(); // another liker

        $component = Livewire::actingAs($user)->test(LikeButton::class, ['target' => $post]);

        expect($component->get('targetType'))->toBe('post')
            ->and($component->get('targetId'))->toBe($post->id)
            ->and($component->get('liked'))->toBeTrue()
            ->and($component->get('count'))->toBe(2);
    });

    it('resolves the morph alias and state for a comment', function () {
        $post = Post::factory()->published()->create();
        $comment = Comment::factory()->for($post)->create();
        $user = User::factory()->asUser()->create();

        $component = Livewire::actingAs($user)->test(LikeButton::class, ['target' => $comment]);

        expect($component->get('targetType'))->toBe('comment')
            ->and($component->get('targetId'))->toBe($comment->id)
            ->and($component->get('liked'))->toBeFalse()
            ->and($component->get('count'))->toBe(0);
    });

    it('shows liked as false for a guest even if others liked it', function () {
        $post = Post::factory()->published()->create();
        Like::factory()->forPost($post)->create();

        $component = Livewire::test(LikeButton::class, ['target' => $post]);

        expect($component->get('liked'))->toBeFalse()
            ->and($component->get('count'))->toBe(1);
    });
});

describe('toggle()', function () {
    it('redirects guests to the login page instead of liking', function () {
        $post = Post::factory()->published()->create();

        Livewire::test(LikeButton::class, ['target' => $post])
            ->call('toggle')
            ->assertRedirect(route('login'));

        expect(Like::count())->toBe(0);
    });

    it('likes a post on first toggle and persists a Like row', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();

        $component = Livewire::actingAs($user)
            ->test(LikeButton::class, ['target' => $post])
            ->call('toggle')
            ->assertSet('liked', true)
            ->assertSet('count', 1);

        expect(Like::where([
            'user_id' => $user->id,
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->exists())->toBeTrue();

        // Regression guard: the count span was previously wired with
        // wire:text="$count" — Alpine evaluates that as JS, not Blade, and
        // it crashed in real browsers ("Public method [$count] not found")
        // despite every server-side Livewire::test() assertion passing.
        $component->assertDontSee('wire:text')->assertSee('1');
    });

    it('unlikes on the second toggle and removes the Like row', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)
            ->test(LikeButton::class, ['target' => $post])
            ->call('toggle')
            ->call('toggle')
            ->assertSet('liked', false)
            ->assertSet('count', 0);

        expect(Like::where([
            'user_id' => $user->id,
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->exists())->toBeFalse();
    });

    it('likes a comment independently of the post it belongs to', function () {
        $post = Post::factory()->published()->create();
        $comment = Comment::factory()->for($post)->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)
            ->test(LikeButton::class, ['target' => $comment])
            ->call('toggle')
            ->assertSet('liked', true);

        expect(Like::where(['target_type' => 'comment', 'target_id' => $comment->id])->count())->toBe(1)
            ->and(Like::where(['target_type' => 'post', 'target_id' => $post->id])->count())->toBe(0);
    });

    it('never lets the displayed count go negative', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();

        $component = Livewire::actingAs($user)->test(LikeButton::class, ['target' => $post]);
        // Force an inconsistent starting state: liked=true but zero real likes.
        $component->set('liked', true)->set('count', 0);

        $component->call('toggle');

        expect($component->get('count'))->toBe(0);
    });
});

describe('live broadcasting', function () {
    it('broadcasts PostLikeCountUpdated when a post is liked', function () {
        Event::fake([PostLikeCountUpdated::class]);
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)->test(LikeButton::class, ['target' => $post])->call('toggle');

        Event::assertDispatched(PostLikeCountUpdated::class,
            fn ($event) => $event->postId === $post->id && $event->likeCount === 1);
    });

    it('does not broadcast for comment likes', function () {
        Event::fake([PostLikeCountUpdated::class]);
        $post = Post::factory()->published()->create();
        $comment = Comment::factory()->for($post)->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)->test(LikeButton::class, ['target' => $comment])->call('toggle');

        Event::assertNotDispatched(PostLikeCountUpdated::class);
    });

    it('registers a dynamic echo listener scoped to the post, only for post targets', function () {
        $post = Post::factory()->published()->create();
        $comment = Comment::factory()->for($post)->create();

        $postListeners = Livewire::test(LikeButton::class, ['target' => $post])->instance()->getListeners();
        $commentListeners = Livewire::test(LikeButton::class, ['target' => $comment])->instance()->getListeners();

        expect($postListeners)->toHaveKey("echo:post.{$post->id},post.like-count-updated")
            ->and($commentListeners)->toBe([]);
    });

    it('updates the count when refreshLikeCount receives a broadcast payload', function () {
        $post = Post::factory()->published()->create();

        $component = Livewire::test(LikeButton::class, ['target' => $post]);
        $component->call('refreshLikeCount', ['likeCount' => 42]);

        expect($component->get('count'))->toBe(42);
    });
});

describe('rate limiting', function () {
    it('stops registering toggles after 30 within a minute', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $component = Livewire::actingAs($user)->test(LikeButton::class, ['target' => $post]);

        for ($i = 0; $i < 30; $i++) {
            $component->call('toggle');
        }
        $countAfter30 = $component->get('count');

        // The 31st call should be silently dropped — no state change.
        $component->call('toggle');

        expect($component->get('count'))->toBe($countAfter30);
    });
});
