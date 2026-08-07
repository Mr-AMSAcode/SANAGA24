<?php

/**
 * tests/Feature/Livewire/Admin/CommentListTest.php
 *
 * Covers App\Livewire\Admin\CommentList: access control, filtering, and
 * the approve/reject/delete moderation actions.
 */

use App\Enums\CommentStatus;
use App\Livewire\Admin\CommentList;
use App\Models\Comment;
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

        Livewire::actingAs($user)->test(CommentList::class)->assertForbidden();
    });

    it('denies editors', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)->test(CommentList::class)->assertForbidden();
    });

    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(CommentList::class)->assertOk();
    });
});

describe('comments()', function () {
    it('filters by status', function () {
        $admin = User::factory()->admin()->create();
        $approved = Comment::factory()->create();
        $rejected = Comment::factory()->rejected()->create();

        $comments = Livewire::actingAs($admin)
            ->test(CommentList::class)
            ->set('statusFilter', 'rejected')
            ->instance()->comments();

        expect($comments->pluck('id')->toArray())->toBe([$rejected->id]);
    });

    it('searches by comment content', function () {
        $admin = User::factory()->admin()->create();
        $match = Comment::factory()->create(['content' => 'A very unique phrase about pelicans']);
        Comment::factory()->create(['content' => 'Something else entirely']);

        $comments = Livewire::actingAs($admin)
            ->test(CommentList::class)
            ->set('search', 'pelicans')
            ->instance()->comments();

        expect($comments->pluck('id')->toArray())->toBe([$match->id]);
    });
});

describe('moderation actions', function () {
    it('rejects an approved comment', function () {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->create();

        Livewire::actingAs($admin)->test(CommentList::class)->call('reject', $comment->id);

        expect($comment->fresh()->status)->toBe(CommentStatus::Rejected);
    });

    it('approves a rejected comment', function () {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->rejected()->create();

        Livewire::actingAs($admin)->test(CommentList::class)->call('approve', $comment->id);

        expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
    });

    it('soft-deletes a comment, removing it from the public thread', function () {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->create();

        Livewire::actingAs($admin)->test(CommentList::class)->call('delete', $comment->id);

        expect(Comment::find($comment->id))->toBeNull()
            ->and(Comment::withTrashed()->find($comment->id))->not->toBeNull();
    });
});
