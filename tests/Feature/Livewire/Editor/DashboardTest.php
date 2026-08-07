<?php

/**
 * tests/Feature/Livewire/Editor/DashboardTest.php
 *
 * Covers App\Livewire\Editor\Dashboard: access control and that every
 * stat is correctly scoped to the authenticated editor's own posts only.
 */

use App\Livewire\Editor\Dashboard;
use App\Models\Post;
use App\Models\PostStats;
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
            ->test(Dashboard::class)
            ->assertForbidden();
    });

    it('denies guests', function () {
        $this->get(route('editor.dashboard'))->assertRedirect(route('login'));
    });

    it('allows editors', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->assertOk();
    });

    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk();
    });
});

describe('stats()', function () {
    it('only counts the authenticated editor\'s own posts', function () {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();

        Post::factory()->published()->withStats()->for($editor, 'editor')->create();
        Post::factory()->published()->withStats()->for($otherEditor, 'editor')->create();

        $stats = Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->instance()->stats();

        expect($stats['total_posts'])->toBe(1)
            ->and($stats['published'])->toBe(1);
    });

    it('buckets posts by status and counts soft-deleted posts as trashed', function () {
        $editor = User::factory()->editor()->create();

        Post::factory()->published()->withStats()->for($editor, 'editor')->create();
        Post::factory()->withStats()->for($editor, 'editor')->create(); // draft
        Post::factory()->archived()->withStats()->for($editor, 'editor')->create();
        $trashed = Post::factory()->published()->withStats()->for($editor, 'editor')->create();
        $trashed->delete();

        $stats = Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->instance()->stats();

        expect($stats['total_posts'])->toBe(4)
            ->and($stats['published'])->toBe(2) // the live one + the trashed one (status untouched by soft delete)
            ->and($stats['drafts'])->toBe(1)
            ->and($stats['archived'])->toBe(1)
            ->and($stats['trashed'])->toBe(1);
    });

    it('sums views, likes and comments across the editor\'s posts', function () {
        $editor = User::factory()->editor()->create();

        $postA = Post::factory()->published()->for($editor, 'editor')->create();
        PostStats::factory()->for($postA)->create(['view_count' => 10, 'like_count' => 2, 'comment_count' => 1]);

        $postB = Post::factory()->published()->for($editor, 'editor')->create();
        PostStats::factory()->for($postB)->create(['view_count' => 5, 'like_count' => 3, 'comment_count' => 4]);

        $stats = Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->instance()->stats();

        expect($stats['total_views'])->toBe(15)
            ->and($stats['total_likes'])->toBe(5)
            ->and($stats['total_comments'])->toBe(5);
    });
});

describe('topPosts() and recentPosts()', function () {
    it('topPosts only includes published posts ordered by views, scoped to the editor', function () {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();

        $low = Post::factory()->published()->for($editor, 'editor')->create();
        PostStats::factory()->for($low)->create(['view_count' => 5]);

        $high = Post::factory()->published()->for($editor, 'editor')->create();
        PostStats::factory()->for($high)->create(['view_count' => 50]);

        $draft = Post::factory()->for($editor, 'editor')->create(); // draft, must be excluded
        PostStats::factory()->for($draft)->create(['view_count' => 999]);

        $othersPost = Post::factory()->published()->for($otherEditor, 'editor')->create();
        PostStats::factory()->for($othersPost)->create(['view_count' => 1000]);

        $topPosts = Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->instance()->topPosts();

        expect($topPosts->pluck('id')->toArray())->toBe([$high->id, $low->id]);
    });

    it('recentPosts includes drafts and is scoped to the editor', function () {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();

        $draft = Post::factory()->for($editor, 'editor')->create();
        Post::factory()->published()->for($otherEditor, 'editor')->create();

        $recent = Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->instance()->recentPosts();

        expect($recent->pluck('id')->toArray())->toBe([$draft->id]);
    });
});
