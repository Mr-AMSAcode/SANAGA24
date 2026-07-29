<?php

/**
 * tests/Feature/Database/ModelsTest.php
 *
 * Tests that every model creates, reads, relates, and scopes correctly
 * using the real PostgreSQL test database.
 *
 * Run: ./vendor/bin/pest tests/Feature/Database/ModelsTest.php
 */

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Picture;
use App\Models\Post;
use App\Models\PostStats;
use App\Models\PostStatus as PostStatusModel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Seed roles and permissions before every test in this file.
 * RefreshDatabase re-migrates on each run but does NOT seed.
 * UserFactory states (editor, admin, asUser) call assignRole() which
 * requires the roles to exist in the database first.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// USER MODEL
// ─────────────────────────────────────────────────────────────────────────────
describe('User model', function () {

    it('can be created and persisted', function () {
        $user = User::factory()->create(['name' => 'miguel nkono', 'email' => 'miguel@gmail.com', 'age' => 21, 'password' => "Test1234"]);

        expect($user->id)->toBeInt()
            ->and($user->name)->toBe('miguel nkono')
            ->and($user->email)->toBe('miguel@gmail.com');

        $this->assertDatabaseHas('users', ['email' => 'miguel@gmail.com']);
    });

    it('generates correct initials', function () {
        $user = User::factory()->make(['name' => 'Jean Pierre Dupont']);
        expect($user->initials())->toBe('JP');

        $user2 = User::factory()->make(['name' => 'Alice']);
        expect($user2->initials())->toBe('A');
    });

    it('enforces unique email at the database level', function () {
        User::factory()->create(['email' => 'duplicate@test.cm']);

        expect(fn() => User::factory()->create(['email' => 'duplicate@test.cm']))
            ->toThrow(QueryException::class);
    });

    it('has posts relationship', function () {
        $editor = User::factory()->editor()->create();
        Post::factory()->count(3)->for($editor, 'editor')->create();

        expect($editor->posts)->toHaveCount(3);
    });

    it('has comments relationship', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();
        Comment::factory()->count(2)->create(['user_id' => $user->id, 'post_id' => $post->id]);

        expect($user->comments)->toHaveCount(2);
    });

    it('has likes relationship', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();
        Like::factory()->forPost($post)->create(['user_id' => $user->id]);

        expect($user->likes)->toHaveCount(1);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// POST MODEL
// ─────────────────────────────────────────────────────────────────────────────
describe('Post model', function () {

    it('can be created with required fields', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create([
            'editor_id' => $editor->id,
            'title' => 'My First Article',
            'slug' => 'my-first-article',
            'status' => PostStatus::Draft,
            'section' => PostSection::Politics,
        ]);

        expect($post->title)->toBe('My First Article')
            ->and($post->status)->toBe(PostStatus::Draft)
            ->and($post->section)->toBe(PostSection::Politics);
    });

    it('casts status to PostStatus enum', function () {
        $post = Post::factory()->published()->create();
        expect($post->status)->toBeInstanceOf(PostStatus::class)
            ->and($post->status)->toBe(PostStatus::Published);
    });

    it('casts section to PostSection enum', function () {
        $post = Post::factory()->inSection(PostSection::Sports)->create();
        expect($post->section)->toBe(PostSection::Sports);
    });

    it('published scope only returns published posts', function () {
        Post::factory()->count(3)->published()->create();
        Post::factory()->count(2)->create(); // drafts

        expect(Post::published()->count())->toBe(3);
    });

    it('can be soft-deleted and restored', function () {
        $post = Post::factory()->create();
        $id = $post->id;

        $post->delete();
        expect(Post::find($id))->toBeNull();
        expect(Post::withTrashed()->find($id))->not->toBeNull();

        $post->restore();
        expect(Post::find($id))->not->toBeNull();
    });

    it('slug is unique at DB level', function () {
        Post::factory()->create(['slug' => 'same-slug']);
        expect(fn() => Post::factory()->create(['slug' => 'same-slug']))
            ->toThrow(QueryException::class);
    });

    it('uses slug as route key', function () {
        $post = Post::factory()->create(['slug' => 'test-slug-123']);
        expect($post->getRouteKeyName())->toBe('slug');
    });

    it('belongs to an editor', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        expect($post->editor->id)->toBe($editor->id);
    });

    it('has a pictures relationship', function () {
        $post = Post::factory()->published()->create();
        Picture::factory()->count(3)->create(['post_id' => $post->id]);

        expect($post->pictures)->toHaveCount(3);
    });

    it('has a stats relationship (1:1)', function () {
        $post = Post::factory()->withStats()->create();

        expect($post->stats)->toBeInstanceOf(PostStats::class);
    });

    it('has a postStatus relationship (1:1)', function () {
        $post = Post::factory()->withStatusRecord()->create();

        expect($post->postStatus)->toBeInstanceOf(PostStatusModel::class);
    });

    it('only one PostStats record per post at DB level', function () {
        $post = Post::factory()->create();
        PostStats::create(['post_id' => $post->id]);

        expect(fn() => PostStats::create(['post_id' => $post->id]))
            ->toThrow(QueryException::class); // unique constraint on post_id
    });

    it('deleting a post cascades to comments', function () {
        $post = Post::factory()->published()->create();
        Comment::factory()->count(3)->create(['post_id' => $post->id]);

        $post->forceDelete();

        expect(Comment::withTrashed()->where('post_id', $post->id)->count())->toBe(0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// COMMENT MODEL
// ─────────────────────────────────────────────────────────────────────────────
describe('Comment model', function () {

    it('can be created as a top-level comment', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();

        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'parent_id' => null,
        ]);

        expect($comment->isTopLevel())->toBeTrue()
            ->and($comment->isReply())->toBeFalse();
    });

    it('can be created as a reply with parent_id set', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();
        $parent = Comment::factory()->create(['post_id' => $post->id]);
        $reply = Comment::factory()->replyTo($parent)->create(['user_id' => $user->id]);

        expect($reply->isReply())->toBeTrue()
            ->and($reply->parent_id)->toBe($parent->id)
            ->and($reply->parent->id)->toBe($parent->id);
    });

    it('parent_id self-references comments table', function () {
        $post = Post::factory()->published()->create();
        $parent = Comment::factory()->create(['post_id' => $post->id]);
        $reply = Comment::factory()->replyTo($parent)->create();

        expect($reply->parent)->toBeInstanceOf(Comment::class);
        expect($parent->replies)->toHaveCount(1);
    });

    it('topLevel scope only returns comments with null parent_id', function () {
        $post = Post::factory()->published()->create();
        Comment::factory()->count(3)->create(['post_id' => $post->id, 'parent_id' => null]);

        $parent = Comment::where('post_id', $post->id)->first();
        Comment::factory()->count(2)->replyTo($parent)->create();

        expect(Comment::where('post_id', $post->id)->topLevel()->count())->toBe(3);
    });

    it('soft-deletes a comment without removing the row', function () {
        $comment = Comment::factory()->create();
        $id = $comment->id;
        $comment->delete();

        expect(Comment::find($id))->toBeNull();
        expect(Comment::withTrashed()->find($id))->not->toBeNull();
    });

    it('reply parent_id becomes null when parent is soft-deleted (nullOnDelete)', function () {
        $post = Post::factory()->published()->create();
        $parent = Comment::factory()->create(['post_id' => $post->id]);
        $reply = Comment::factory()->replyTo($parent)->create();

        // Force-delete parent to trigger DB-level nullOnDelete
        $parent->forceDelete();
        $reply->refresh();

        expect($reply->parent_id)->toBeNull();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// LIKE MODEL — polymorphic
// ─────────────────────────────────────────────────────────────────────────────
describe('Like model (polymorphic)', function () {

    it('can like a post', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();

        $like = Like::factory()->forPost($post)->create(['user_id' => $user->id]);

        expect($like->target_type)->toBe('post')
            ->and($like->target_id)->toBe($post->id)
            ->and($like->target)->toBeInstanceOf(Post::class);
    });

    it('can like a comment', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $like = Like::factory()->forComment($comment)->create(['user_id' => $user->id]);

        expect($like->target_type)->toBe('comment')
            ->and($like->target)->toBeInstanceOf(Comment::class);
    });

    it('prevents duplicate likes (unique constraint)', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();

        Like::factory()->forPost($post)->create(['user_id' => $user->id]);

        expect(fn() => Like::factory()->forPost($post)->create(['user_id' => $user->id]))
            ->toThrow(QueryException::class);
    });

    it('prevents invalid target_type via CHECK constraint', function () {
        $user = User::factory()->create();

        expect(fn() => Like::create([
            'user_id' => $user->id,
            'target_type' => 'invalid_type', // NOT 'post' or 'comment'
            'target_id' => 1,
            'created_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('post morphMany returns correct likes', function () {
        $post = Post::factory()->published()->create();
        Like::factory()->forPost($post)->count(3)->create();

        expect($post->likes)->toHaveCount(3);
        expect($post->likes->first()->target_type)->toBe('post');
    });

    it('comment morphMany returns correct likes', function () {
        $post = Post::factory()->published()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);
        Like::factory()->forComment($comment)->count(2)->create();

        expect($comment->likes)->toHaveCount(2);
    });

    it('isLikedBy returns true when user has liked', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();
        Like::factory()->forPost($post)->create(['user_id' => $user->id]);

        expect($post->isLikedBy($user))->toBeTrue();
    });

    it('isLikedBy returns false when user has not liked', function () {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();

        expect($post->isLikedBy($user))->toBeFalse();
    });

    it('has no updated_at column', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->create();
        $like = Like::factory()->forPost($post)->create(['user_id' => $user->id]);

        // The model should not have an updated_at property
        expect(array_key_exists('updated_at', $like->toArray()))->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// PICTURE MODEL
// ─────────────────────────────────────────────────────────────────────────────
describe('Picture model', function () {

    it('can be created and attached to a post', function () {
        $post = Post::factory()->published()->create();
        $picture = Picture::factory()->create(['post_id' => $post->id]);

        expect($picture->post_id)->toBe($post->id);
        expect($post->pictures)->toHaveCount(1);
    });

    it('can be created without a post (nullable post_id)', function () {
        $picture = Picture::factory()->create(['post_id' => null]);
        expect($picture->post_id)->toBeNull();
    });

    it('only one featured picture per post', function () {
        $post = Post::factory()->published()->create();
        Picture::factory()->featured()->create(['post_id' => $post->id]);

        expect(fn() => Picture::factory()->featured()->create(['post_id' => $post->id]))
            ->toThrow(QueryException::class); // unique on (post_id, is_featured)
    });

    it('featured scope returns only featured pictures', function () {
        $post = Post::factory()->published()->create();
        Picture::factory()->featured()->create(['post_id' => $post->id]);
        Picture::factory()->create(['post_id' => $post->id]);
        Picture::factory()->create(['post_id' => $post->id]);

        expect(Picture::where('post_id', $post->id)->featured()->count())->toBe(1);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// POST STATS MODEL
// ─────────────────────────────────────────────────────────────────────────────
describe('PostStats model', function () {

    it('is created with zeroed counters by default', function () {
        $post = Post::factory()->create();
        $stats = PostStats::create(['post_id' => $post->id]);

        expect($stats->view_count)->toBe(0)
            ->and($stats->like_count)->toBe(0)
            ->and($stats->comment_count)->toBe(0);
    });

    it('increments view count', function () {
        $post = Post::factory()->create();
        $stats = PostStats::create(['post_id' => $post->id]);

        $stats->incrementViews();
        $stats->incrementViews();

        expect($stats->fresh()->view_count)->toBe(2);
    });

    it('cannot have two stats records for the same post', function () {
        $post = Post::factory()->create();
        PostStats::create(['post_id' => $post->id]);

        expect(fn() => PostStats::create(['post_id' => $post->id]))
            ->toThrow(QueryException::class);
    });

    it('is deleted when the post is deleted', function () {
        $post = Post::factory()->withStats()->create();
        $statsId = $post->stats->id;

        $post->forceDelete();

        expect(PostStats::find($statsId))->toBeNull();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// POST STATUS MODEL
// ─────────────────────────────────────────────────────────────────────────────
describe('PostStatus model', function () {

    it('can be created with an active period', function () {
        $post = Post::factory()->published()->create();
        $status = PostStatusModel::create([
            'post_id' => $post->id,
            'active_period_start' => now()->subDay(),
            'active_period_end' => null,
            'is_archived' => false,
        ]);

        expect($status->isCurrentlyActive())->toBeTrue();
    });

    it('is not active when archived', function () {
        $post = Post::factory()->create();
        $status = PostStatusModel::factory()->archived()->create(['post_id' => $post->id]);

        expect($status->isCurrentlyActive())->toBeFalse();
    });

    it('archive() sets is_archived and active_period_end', function () {
        $post = Post::factory()->published()->create();
        $status = PostStatusModel::create([
            'post_id' => $post->id,
            'active_period_start' => now()->subDays(10),
        ]);

        $status->archive();
        $status->refresh();

        expect($status->is_archived)->toBeTrue()
            ->and($status->active_period_end)->not->toBeNull();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// POST STATUS ENUM
// ─────────────────────────────────────────────────────────────────────────────
describe('PostStatus enum transitions', function () {

    it('draft can transition to published', function () {
        expect(PostStatus::Draft->canTransitionTo(PostStatus::Published))->toBeTrue();
    });

    it('draft cannot transition to archived directly', function () {
        expect(PostStatus::Draft->canTransitionTo(PostStatus::Archived))->toBeFalse();
    });

    it('published can transition to archived', function () {
        expect(PostStatus::Published->canTransitionTo(PostStatus::Archived))->toBeTrue();
    });

    it('archived can be re-published', function () {
        expect(PostStatus::Archived->canTransitionTo(PostStatus::Published))->toBeTrue();
    });
});
