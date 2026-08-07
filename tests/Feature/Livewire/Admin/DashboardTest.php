<?php

/**
 * tests/Feature/Livewire/Admin/DashboardTest.php
 *
 * Covers App\Livewire\Admin\Dashboard: access control, and that every
 * stat aggregates across ALL editors site-wide (unlike the editor
 * dashboard's per-editor scoping).
 */

use App\Livewire\Admin\Dashboard;
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

    it('denies editors', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->assertForbidden();
    });

    it('denies guests', function () {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    });

    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk();
    });
});

describe('siteStats()', function () {
    it('aggregates across every editor, not just one', function () {
        $admin = User::factory()->admin()->create();
        $editorA = User::factory()->editor()->create();
        $editorB = User::factory()->editor()->create();

        Post::factory()->published()->withStats()->for($editorA, 'editor')->create();
        Post::factory()->published()->withStats()->for($editorB, 'editor')->create();
        $trashed = Post::factory()->withStats()->for($editorA, 'editor')->create();
        $trashed->delete();

        $stats = Livewire::actingAs($admin)->test(Dashboard::class)->instance()->siteStats();

        expect($stats['total_users'])->toBe(3) // admin + 2 editors
            ->and($stats['total_editors'])->toBe(2)
            ->and($stats['total_admins'])->toBe(1)
            ->and($stats['total_posts'])->toBe(3) // includes the trashed one
            ->and($stats['published'])->toBe(2);
    });

    it('sums views/likes/comments from post_stats across all posts', function () {
        $admin = User::factory()->admin()->create();
        $editorA = User::factory()->editor()->create();
        $editorB = User::factory()->editor()->create();

        $postA = Post::factory()->published()->for($editorA, 'editor')->create();
        PostStats::factory()->for($postA)->create(['view_count' => 10, 'like_count' => 1, 'comment_count' => 2]);

        $postB = Post::factory()->published()->for($editorB, 'editor')->create();
        PostStats::factory()->for($postB)->create(['view_count' => 20, 'like_count' => 3, 'comment_count' => 4]);

        $stats = Livewire::actingAs($admin)->test(Dashboard::class)->instance()->siteStats();

        expect($stats['total_views'])->toBe(30)
            ->and($stats['total_likes'])->toBe(4)
            ->and($stats['total_comments'])->toBe(6);
    });
});

describe('topEditors()', function () {
    it('ranks editors by their published post count, ignoring drafts', function () {
        $admin = User::factory()->admin()->create();
        $prolific = User::factory()->editor()->create();
        $quiet = User::factory()->editor()->create();

        Post::factory()->published()->count(3)->for($prolific, 'editor')->create();
        Post::factory()->count(5)->for($prolific, 'editor')->create(); // drafts, must not count
        Post::factory()->published()->count(1)->for($quiet, 'editor')->create();

        $top = Livewire::actingAs($admin)->test(Dashboard::class)->instance()->topEditors();

        expect($top->first()->id)->toBe($prolific->id)
            ->and($top->first()->published_count)->toBe(3)
            ->and($top->last()->published_count)->toBe(1);
    });
});

describe('recentPosts() and recentUsers()', function () {
    it('recentPosts is not scoped to a single editor', function () {
        $admin = User::factory()->admin()->create();
        $editorA = User::factory()->editor()->create();
        $editorB = User::factory()->editor()->create();

        Post::factory()->for($editorA, 'editor')->create();
        Post::factory()->for($editorB, 'editor')->create();

        $recent = Livewire::actingAs($admin)->test(Dashboard::class)->instance()->recentPosts();

        expect($recent)->toHaveCount(2);
    });

    it('recentUsers lists the most recently registered accounts first', function () {
        $admin = User::factory()->admin()->create(['created_at' => now()->subDays(10)]);
        $older = User::factory()->asUser()->create(['created_at' => now()->subDays(5)]);
        $newer = User::factory()->asUser()->create(['created_at' => now()]);

        $recent = Livewire::actingAs($admin)->test(Dashboard::class)->instance()->recentUsers();

        expect($recent->first()->id)->toBe($newer->id);
    });
});
