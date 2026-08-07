<?php

/**
 * tests/Feature/Livewire/Pages/PostShowTest.php
 *
 * Covers App\Livewire\Pages\PostShow: public visibility rules for
 * drafts vs published posts, the view-count increment, initial like
 * state, and the related/latest posts sidebars.
 */

use App\Enums\PostSection;
use App\Livewire\Pages\PostShow;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('visibility', function () {
    it('lets guests view a published post', function () {
        $post = Post::factory()->published()->create();

        Livewire::test(PostShow::class, ['post' => $post])->assertOk();
    });

    it('denies guests viewing a draft', function () {
        $post = Post::factory()->create(); // draft

        Livewire::test(PostShow::class, ['post' => $post])->assertForbidden();
    });

    it('denies another editor from previewing someone else\'s draft', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create(); // owned by a different, factory-generated editor

        Livewire::actingAs($editor)
            ->test(PostShow::class, ['post' => $post])
            ->assertForbidden();
    });

    it('lets the owning editor preview their own draft', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create(); // draft

        Livewire::actingAs($editor)
            ->test(PostShow::class, ['post' => $post])
            ->assertOk();
    });

    it('lets an admin preview any draft', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(); // draft

        Livewire::actingAs($admin)
            ->test(PostShow::class, ['post' => $post])
            ->assertOk();
    });
});

describe('mount()', function () {
    it('increments the view count on every visit', function () {
        $post = Post::factory()->published()->withStats()->create();
        $baseline = $post->stats->view_count;

        Livewire::test(PostShow::class, ['post' => $post]);
        Livewire::test(PostShow::class, ['post' => $post]);

        expect($post->stats->fresh()->view_count)->toBe($baseline + 2);
    });

    it('creates a stats row on the fly, starting at 1 view, if one is missing', function () {
        $post = Post::factory()->published()->create(); // no ->withStats()

        Livewire::test(PostShow::class, ['post' => $post])->assertOk();

        expect(\App\Models\PostStats::where('post_id', $post->id)->value('view_count'))->toBe(1);
    });

    it('reports whether the authenticated visitor already liked the post', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        Like::factory()->forPost($post)->for($user, 'user')->create();

        $component = Livewire::actingAs($user)->test(PostShow::class, ['post' => $post]);

        expect($component->get('liked'))->toBeTrue()
            ->and($component->get('likeCount'))->toBe(1);
    });

    it('shows liked as false for a guest', function () {
        $post = Post::factory()->published()->create();

        $component = Livewire::test(PostShow::class, ['post' => $post]);

        expect($component->get('liked'))->toBeFalse();
    });
});

describe('video display', function () {
    it('renders an iframe for an embedded video', function () {
        $post = Post::factory()->published()->create();
        \App\Models\Video::factory()->embed('youtube')->for($post)->create(['url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ']);

        Livewire::test(PostShow::class, ['post' => $post])
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false)
            ->assertSeeHtml('<iframe');
    });

    it('renders a native video player for an uploaded video', function () {
        $post = Post::factory()->published()->create();
        \App\Models\Video::factory()->upload()->for($post)->create(['url' => '/storage/posts/videos/clip.mp4']);

        Livewire::test(PostShow::class, ['post' => $post])
            ->assertSee('/storage/posts/videos/clip.mp4', false)
            ->assertSeeHtml('<video');
    });

    it('shows nothing extra when the post has no videos', function () {
        $post = Post::factory()->published()->create();

        Livewire::test(PostShow::class, ['post' => $post])
            ->assertDontSeeHtml('<iframe')
            ->assertDontSeeHtml('<video');
    });
});

describe('relatedPosts() and latestPosts()', function () {
    it('relatedPosts only includes other published posts from the same section', function () {
        $post = Post::factory()->published()->inSection(PostSection::Science)->create();
        $related = Post::factory()->published()->inSection(PostSection::Science)->create();
        Post::factory()->published()->inSection(PostSection::Sports)->create(); // different section
        Post::factory()->inSection(PostSection::Science)->create(); // draft, same section

        $result = Livewire::test(PostShow::class, ['post' => $post])->instance()->relatedPosts();

        expect($result->pluck('id')->toArray())->toBe([$related->id]);
    });

    it('latestPosts excludes the current post and any drafts', function () {
        $post = Post::factory()->published()->create();
        $other = Post::factory()->published()->create();
        Post::factory()->create(); // draft

        $result = Livewire::test(PostShow::class, ['post' => $post])->instance()->latestPosts();

        expect($result->pluck('id')->toArray())->toBe([$other->id]);
    });
});

describe('comment-count-changed event', function () {
    it('refreshes the post so the comment badge reflects the latest count', function () {
        $post = Post::factory()->published()->withStats()->create();
        $baseline = $post->stats->comment_count;

        $component = Livewire::test(PostShow::class, ['post' => $post]);

        // Simulate what the Comment model hook already did to the DB
        // (see CommentThreadTest for the end-to-end path) and confirm
        // the event handler re-pulls it rather than double-incrementing.
        $post->stats()->increment('comment_count');

        $component->dispatch('comment-count-changed');

        expect($component->instance()->post->stats->comment_count)->toBe($baseline + 1);
    });
});
