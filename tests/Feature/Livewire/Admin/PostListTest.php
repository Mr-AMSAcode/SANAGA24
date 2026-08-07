<?php

/**
 * tests/Feature/Livewire/Admin/PostListTest.php
 *
 * Covers App\Livewire\Admin\PostList: access control, that (unlike the
 * editor list) it spans every editor, filtering/sorting/trashed toggle,
 * single-row actions (delete/restore/forceDelete) and bulk actions.
 */

use App\Enums\PostStatus;
use App\Livewire\Admin\PostList;
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

        Livewire::actingAs($user)->test(PostList::class)->assertForbidden();
    });

    it('denies editors', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)->test(PostList::class)->assertForbidden();
    });

    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(PostList::class)->assertOk();
    });

    it('actually renders the post data, not just a bare 200 response', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['title' => 'A Uniquely Identifiable Headline']);

        Livewire::actingAs($admin)
            ->test(PostList::class)
            ->assertSee('A Uniquely Identifiable Headline')
            ->assertSee($post->editor->name);
    });
});

describe('posts()', function () {
    it('lists posts from every editor, not just one', function () {
        $admin = User::factory()->admin()->create();
        $editorA = User::factory()->editor()->create();
        $editorB = User::factory()->editor()->create();

        Post::factory()->for($editorA, 'editor')->create();
        Post::factory()->for($editorB, 'editor')->create();

        $posts = Livewire::actingAs($admin)->test(PostList::class)->instance()->posts();

        expect($posts)->toHaveCount(2);
    });

    it('excludes trashed posts unless showTrashed is enabled', function () {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $live = Post::factory()->for($editor, 'editor')->create();
        $trashed = Post::factory()->for($editor, 'editor')->create();
        $trashed->delete();

        $withoutTrashed = Livewire::actingAs($admin)->test(PostList::class)->instance()->posts();
        expect($withoutTrashed->pluck('id')->toArray())->toBe([$live->id]);

        $withTrashed = Livewire::actingAs($admin)
            ->test(PostList::class)
            ->set('showTrashed', true)
            ->instance()->posts();
        expect($withTrashed->pluck('id')->sort()->values()->toArray())
            ->toBe(collect([$live->id, $trashed->id])->sort()->values()->toArray());
    });

    it('filters by editor', function () {
        $admin = User::factory()->admin()->create();
        $editorA = User::factory()->editor()->create();
        $editorB = User::factory()->editor()->create();
        $mine = Post::factory()->for($editorA, 'editor')->create();
        Post::factory()->for($editorB, 'editor')->create();

        $posts = Livewire::actingAs($admin)
            ->test(PostList::class)
            ->set('editorFilter', (string) $editorA->id)
            ->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$mine->id]);
    });
});

describe('sort()', function () {
    it('resets to descending when switching to a different column (regression)', function () {
        $admin = User::factory()->admin()->create();

        // Toggle title to ascending first...
        $component = Livewire::actingAs($admin)->test(PostList::class)
            ->call('sort', 'title')
            ->call('sort', 'title');
        expect($component->get('sortDir'))->toBe('asc');

        // ...then switching to a brand new column must NOT just flip again;
        // it must land on a stable 'desc' default regardless of prior state.
        $component->call('sort', 'section');
        expect($component->get('sortBy'))->toBe('section')
            ->and($component->get('sortDir'))->toBe('desc');
    });
});

describe('single-row actions', function () {
    it('deletes a post', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();

        Livewire::actingAs($admin)->test(PostList::class)->call('delete', $post->id);

        expect(Post::find($post->id))->toBeNull();
    });

    it('restores a trashed post', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();
        $post->delete();

        Livewire::actingAs($admin)->test(PostList::class)->call('restore', $post->id);

        expect(Post::find($post->id))->not->toBeNull();
    });

    it('permanently deletes a trashed post', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();
        $post->delete();

        Livewire::actingAs($admin)->test(PostList::class)->call('forceDelete', $post->id);

        expect(Post::withTrashed()->find($post->id))->toBeNull();
    });
});

describe('bulk actions', function () {
    it('bulk-deletes every selected post', function () {
        $admin = User::factory()->admin()->create();
        $posts = Post::factory()->count(3)->create();

        Livewire::actingAs($admin)
            ->test(PostList::class)
            ->set('selected', $posts->pluck('id')->toArray())
            ->call('bulkDelete');

        expect(Post::count())->toBe(0);
    });

    it('bulk-publishes only the selected drafts, leaving other statuses untouched', function () {
        $admin = User::factory()->admin()->create();
        $draft = Post::factory()->create();
        $archived = Post::factory()->archived()->create();

        Livewire::actingAs($admin)
            ->test(PostList::class)
            ->set('selected', [$draft->id, $archived->id])
            ->call('bulkPublish');

        expect($draft->fresh()->status)->toBe(PostStatus::Published)
            ->and($archived->fresh()->status)->toBe(PostStatus::Archived);
    });

    it('clears the selection after a bulk action', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test(PostList::class)
            ->set('selected', [$post->id])
            ->call('bulkDelete');

        expect($component->get('selected'))->toBe([]);
    });
});
