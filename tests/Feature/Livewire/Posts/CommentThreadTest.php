<?php

/**
 * tests/Feature/Livewire/Posts/CommentThreadTest.php
 *
 * Covers App\Livewire\Posts\CommentThread: posting top-level comments
 * and replies, deleting comments, and the reply-expansion UI state.
 */

use App\Events\CommentPosted;
use App\Livewire\Posts\CommentThread;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('postComment()', function () {
    it('requires authentication', function () {
        $post = Post::factory()->published()->create();

        Livewire::test(CommentThread::class, ['post' => $post])
            ->set('newComment', 'A perfectly valid comment body.')
            ->call('postComment')
            ->assertForbidden();
    });

    it('persists the comment content and clears the input', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->set('newComment', 'This restaurant review really nailed it.')
            ->call('postComment')
            ->assertHasNoErrors()
            ->assertSet('newComment', '');

        $comment = $post->allComments()->latest()->firstOrFail();
        expect($comment->content)->toBe('This restaurant review really nailed it.')
            ->and($comment->user_id)->toBe($user->id)
            ->and($comment->parent_id)->toBeNull();
    });

    it('requires at least 2 characters', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->set('newComment', 'x')
            ->call('postComment')
            ->assertHasErrors(['newComment']);
    });
});

describe('reply flow', function () {
    it('opens and cancels the reply box', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $parent = Comment::factory()->for($post)->create();

        $component = Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->call('openReply', $parent->id);

        expect($component->get('replyingToId'))->toBe($parent->id);

        $component->call('cancelReply');
        expect($component->get('replyingToId'))->toBeNull();
    });

    it('posts a reply, links it to the parent, and auto-expands the thread', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $parent = Comment::factory()->for($post)->create();

        $component = Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->call('openReply', $parent->id)
            ->set('replyBody', 'Totally agree with your take on this.')
            ->call('postReply')
            ->assertHasNoErrors();

        $reply = Comment::where('parent_id', $parent->id)->firstOrFail();
        expect($reply->content)->toBe('Totally agree with your take on this.')
            ->and($reply->user_id)->toBe($user->id)
            ->and($component->get('expandedReplies'))->toContain($parent->id)
            ->and($component->get('replyingToId'))->toBeNull();
    });

    it('notifies the parent comment\'s author of the reply', function () {
        Notification::fake();
        $post = Post::factory()->published()->create();
        $author = User::factory()->asUser()->create();
        $replier = User::factory()->asUser()->create();
        $parent = Comment::factory()->for($post)->for($author, 'user')->create();

        Livewire::actingAs($replier)
            ->test(CommentThread::class, ['post' => $post])
            ->call('openReply', $parent->id)
            ->set('replyBody', 'Great point, thanks for sharing.')
            ->call('postReply');

        Notification::assertSentTo($author, CommentReplyNotification::class);
    });

    it('emails the parent comment\'s author, not just a database notification', function () {
        Notification::fake();
        $post = Post::factory()->published()->create();
        $author = User::factory()->asUser()->create();
        $replier = User::factory()->asUser()->create(['name' => 'Kofi Reader']);
        $parent = Comment::factory()->for($post)->for($author, 'user')->create();

        Livewire::actingAs($replier)
            ->test(CommentThread::class, ['post' => $post])
            ->call('openReply', $parent->id)
            ->set('replyBody', 'Great point, thanks for sharing.')
            ->call('postReply');

        Notification::assertSentTo(
            $author,
            CommentReplyNotification::class,
            function ($notification, $channels) use ($author, $post) {
                expect($channels)->toContain('mail');

                $mail = $notification->toMail($author);

                expect($mail->subject)->toBe('New reply to your comment on Sanaga24')
                    ->and(implode(' ', $mail->introLines))->toContain('Kofi Reader')
                    ->and(implode(' ', $mail->introLines))->toContain('Great point, thanks for sharing.')
                    ->and($mail->actionUrl)->toContain(route('posts.show', $post));

                return true;
            }
        );
    });

    it('does not notify yourself when replying to your own comment', function () {
        Notification::fake();
        $post = Post::factory()->published()->create();
        $author = User::factory()->asUser()->create();
        $parent = Comment::factory()->for($post)->for($author, 'user')->create();

        Livewire::actingAs($author)
            ->test(CommentThread::class, ['post' => $post])
            ->call('openReply', $parent->id)
            ->set('replyBody', 'Adding more context to my own comment.')
            ->call('postReply');

        Notification::assertNothingSentTo($author);
    });

    it('refuses a reply targeting a comment from a different post', function () {
        $post = Post::factory()->published()->create();
        $otherPost = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $foreignComment = Comment::factory()->for($otherPost, 'post')->create();

        expect(fn () => Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->set('replyingToId', $foreignComment->id)
            ->set('replyBody', 'Trying to reply across posts.')
            ->call('postReply')
        )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        expect(Comment::where('parent_id', $foreignComment->id)->exists())->toBeFalse();
    });

    it('toggles a thread\'s expanded state', function () {
        $post = Post::factory()->published()->create();
        $parent = Comment::factory()->for($post)->create();

        $component = Livewire::test(CommentThread::class, ['post' => $post])
            ->call('toggleReplies', $parent->id);
        expect($component->get('expandedReplies'))->toContain($parent->id);

        $component->call('toggleReplies', $parent->id);
        expect($component->get('expandedReplies'))->not->toContain($parent->id);
    });
});

describe('deleteComment()', function () {
    it('lets the author delete their own comment', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $comment = Comment::factory()->for($post)->for($user, 'user')->create();

        Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->call('confirmDelete', $comment->id)
            ->call('deleteComment');

        expect(Comment::find($comment->id))->toBeNull();
    });

    it('refuses to let one user delete another user\'s comment', function () {
        $post = Post::factory()->published()->create();
        $author = User::factory()->asUser()->create();
        $intruder = User::factory()->asUser()->create();
        $comment = Comment::factory()->for($post)->for($author, 'user')->create();

        Livewire::actingAs($intruder)
            ->test(CommentThread::class, ['post' => $post])
            ->call('confirmDelete', $comment->id)
            ->call('deleteComment')
            ->assertForbidden();

        expect(Comment::find($comment->id))->not->toBeNull();
    });

    it('lets an admin delete any comment', function () {
        $post = Post::factory()->published()->create();
        $admin = User::factory()->admin()->create();
        $author = User::factory()->asUser()->create();
        $comment = Comment::factory()->for($post)->for($author, 'user')->create();

        Livewire::actingAs($admin)
            ->test(CommentThread::class, ['post' => $post])
            ->call('confirmDelete', $comment->id)
            ->call('deleteComment');

        expect(Comment::find($comment->id))->toBeNull();
    });
});

describe('comments()', function () {
    it('only lists top-level comments, with their replies eager-loaded', function () {
        $post = Post::factory()->published()->create();
        $topLevel = Comment::factory()->for($post)->create();
        $reply = Comment::factory()->for($post)->replyTo($topLevel)->create();

        $comments = Livewire::test(CommentThread::class, ['post' => $post])->instance()->comments();

        expect($comments->pluck('id')->toArray())->toBe([$topLevel->id])
            ->and($comments->first()->replies_count)->toBe(1)
            ->and($comments->first()->replies->pluck('id')->toArray())->toBe([$reply->id]);
    });

    it('hides rejected top-level comments from the public thread', function () {
        $post = Post::factory()->published()->create();
        $visible = Comment::factory()->for($post)->create();
        Comment::factory()->for($post)->rejected()->create();

        $comments = Livewire::test(CommentThread::class, ['post' => $post])->instance()->comments();

        expect($comments->pluck('id')->toArray())->toBe([$visible->id]);
    });

    it('hides rejected replies and excludes them from the reply count', function () {
        $post = Post::factory()->published()->create();
        $topLevel = Comment::factory()->for($post)->create();
        $visibleReply = Comment::factory()->for($post)->replyTo($topLevel)->create();
        Comment::factory()->for($post)->replyTo($topLevel)->rejected()->create();

        $comments = Livewire::test(CommentThread::class, ['post' => $post])->instance()->comments();

        expect($comments->first()->replies_count)->toBe(1)
            ->and($comments->first()->replies->pluck('id')->toArray())->toBe([$visibleReply->id]);
    });
});

describe('live broadcasting', function () {
    it('broadcasts CommentPosted when a top-level comment is posted', function () {
        Event::fake([CommentPosted::class]);
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->set('newComment', 'A perfectly valid comment body.')
            ->call('postComment');

        Event::assertDispatched(CommentPosted::class, fn ($event) => $event->postId === $post->id);
    });

    it('broadcasts CommentPosted when a comment is deleted', function () {
        Event::fake([CommentPosted::class]);
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $comment = Comment::factory()->for($post)->for($user, 'user')->create();

        Livewire::actingAs($user)
            ->test(CommentThread::class, ['post' => $post])
            ->call('confirmDelete', $comment->id)
            ->call('deleteComment');

        Event::assertDispatched(CommentPosted::class, fn ($event) => $event->postId === $post->id);
    });

    it('registers a dynamic echo listener scoped to this post', function () {
        $post = Post::factory()->published()->create();

        $listeners = Livewire::test(CommentThread::class, ['post' => $post])->instance()->getListeners();

        expect($listeners)->toHaveKey("echo:post.{$post->id},comment.posted");
    });
});

describe('rate limiting', function () {
    it('allows up to 5 comments per minute', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $component = Livewire::actingAs($user)->test(CommentThread::class, ['post' => $post]);

        for ($i = 0; $i < 5; $i++) {
            $component->set('newComment', "Comment number {$i} with enough length.")
                ->call('postComment')
                ->assertHasNoErrors();
        }

        expect(Comment::count())->toBe(5);
    });

    it('blocks the 6th comment within the same minute', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $component = Livewire::actingAs($user)->test(CommentThread::class, ['post' => $post]);

        for ($i = 0; $i < 5; $i++) {
            $component->set('newComment', "Comment number {$i} with enough length.")->call('postComment');
        }

        $component->set('newComment', 'One comment too many for this minute.')
            ->call('postComment')
            ->assertHasErrors(['newComment']);

        expect(Comment::count())->toBe(5);
    });

    it('shares the rate limit budget between top-level comments and replies', function () {
        $post = Post::factory()->published()->create();
        $user = User::factory()->asUser()->create();
        $parent = Comment::factory()->for($post)->create();
        $component = Livewire::actingAs($user)->test(CommentThread::class, ['post' => $post]);

        for ($i = 0; $i < 5; $i++) {
            $component->set('newComment', "Comment number {$i} with enough length.")->call('postComment');
        }

        $component->call('openReply', $parent->id)
            ->set('replyBody', 'Trying to sneak one more in via a reply.')
            ->call('postReply')
            ->assertHasErrors(['replyBody']);
    });
});
