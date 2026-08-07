<?php

/**
 * tests/Feature/Livewire/Editor/PostListTest.php
 *
 * Covers App\Livewire\Editor\PostList: access control, that the list is
 * scoped to the editor's own posts, filtering/sorting, and the quick
 * publish/delete row actions (including that they stay ownership-scoped).
 */

use App\Enums\PostStatus;
use App\Livewire\Editor\PostList;
use App\Models\Post;
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

        Livewire::actingAs($user)
            ->test(PostList::class)
            ->assertForbidden();
    });

    it('allows editors', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostList::class)
            ->assertOk();
    });

    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PostList::class)
            ->assertOk();
    });
});

describe('posts()', function () {
    it('only lists the authenticated editor\'s own posts', function () {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();

        $mine = Post::factory()->for($editor, 'editor')->create();
        Post::factory()->for($otherEditor, 'editor')->create();

        $posts = Livewire::actingAs($editor)->test(PostList::class)->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$mine->id]);
    });

    it('filters by status and section', function () {
        $editor = User::factory()->editor()->create();
        $draftSports = Post::factory()->inSection(\App\Enums\PostSection::Sports)->for($editor, 'editor')->create();
        Post::factory()->published()->inSection(\App\Enums\PostSection::World)->for($editor, 'editor')->create();

        $posts = Livewire::actingAs($editor)
            ->test(PostList::class)
            ->set('statusFilter', 'draft')
            ->set('sectionFilter', 'sports')
            ->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$draftSports->id]);
    });

    it('searches by title', function () {
        $editor = User::factory()->editor()->create();
        $match = Post::factory()->for($editor, 'editor')->create(['title' => 'A Unique Headline About Whales']);
        Post::factory()->for($editor, 'editor')->create(['title' => 'Something Else Entirely']);

        $posts = Livewire::actingAs($editor)
            ->test(PostList::class)
            ->set('search', 'whales')
            ->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$match->id]);
    });
});

describe('sort()', function () {
    it('defaults to descending and toggles to ascending on the same column', function () {
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)->test(PostList::class);

        $component->call('sort', 'title');
        expect($component->get('sortBy'))->toBe('title')
            ->and($component->get('sortDir'))->toBe('desc');

        $component->call('sort', 'title');
        expect($component->get('sortDir'))->toBe('asc');
    });

    it('resets to descending when switching to a different column', function () {
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)->test(PostList::class)
            ->call('sort', 'title')
            ->call('sort', 'title'); // now asc

        expect($component->get('sortDir'))->toBe('asc');

        $component->call('sort', 'section');
        expect($component->get('sortBy'))->toBe('section')
            ->and($component->get('sortDir'))->toBe('desc');
    });

    it('ignores unknown columns', function () {
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)->test(PostList::class);
        $component->call('sort', 'editor_id');

        expect($component->get('sortBy'))->toBe('created_at');
    });
});

describe('quick actions', function () {
    it('publishes one of the editor\'s own draft posts', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostList::class)
            ->call('publish', $post->id);

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Published)
            ->and($post->postStatus->active_period_start)->not->toBeNull();
    });

    it('refuses to publish another editor\'s post', function () {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();
        $post = Post::factory()->for($otherEditor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostList::class)
            ->call('publish', $post->id)
            ->assertForbidden();

        expect($post->fresh()->status)->toBe(PostStatus::Draft);
    });

    it('soft-deletes one of the editor\'s own posts', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostList::class)
            ->call('delete', $post->id);

        expect(Post::find($post->id))->toBeNull();
    });

    it('refuses to delete another editor\'s post', function () {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();
        $post = Post::factory()->for($otherEditor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostList::class)
            ->call('delete', $post->id)
            ->assertForbidden();

        expect(Post::find($post->id))->not->toBeNull();
    });
});
