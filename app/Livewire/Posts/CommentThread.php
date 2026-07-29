<?php

namespace App\Livewire\Posts;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Renders the full comment thread for a post.
 * Embedded inside PostShow via <livewire:posts.comment-thread :post="$post" />
 *
 * Responsibilities:
 *  - Display top-level comments with paginated loading
 *  - Expand/collapse reply threads per comment
 *  - Add a new top-level comment
 *  - Delete own comment (or any comment if admin)
 *
 * Each comment's reply sub-thread is handled inline here — no separate
 * child component is needed since replies are only one level deep.
 */
class CommentThread extends Component
{
    use WithPagination;

    #[Locked]
    public Post $post;

    // ─────────────────────────────────────────────────
    // New top-level comment
    // ─────────────────────────────────────────────────

    #[Rule('required|string|min:2|max:5000')]
    public string $newComment = '';

    // ─────────────────────────────────────────────────
    // Inline reply state
    // ─────────────────────────────────────────────────

    /**
     * The comment ID we are currently replying to.
     * Null = reply box is closed.
     */
    public ?int $replyingToId = null;

    #[Rule('required|string|min:2|max:5000')]
    public string $replyBody = '';

    /**
     * Comment IDs whose reply threads are expanded in the UI.
     */
    public array $expandedReplies = [];

    /**
     * Comment ID of any delete confirmation dialog currently open.
     */
    public ?int $confirmDeleteId = null;

    // ─────────────────────────────────────────────────
    // Post a top-level comment
    // ─────────────────────────────────────────────────

    public function postComment(): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()->can('comment.create'), 403);

        $this->validateOnly('newComment');

        Comment::create([
            'post_id' => $this->post->id,
            'user_id' => auth()->id(),
            'parent_id' => null,
            'body' => $this->newComment,
        ]);

        $this->newComment = '';

        // Notify the parent PostShow component to update its comment count badge
        $this->dispatch('comment-posted');
    }

    // ─────────────────────────────────────────────────
    // Reply to a specific comment
    // ─────────────────────────────────────────────────

    public function openReply(int $commentId): void
    {
        abort_unless(auth()->check(), 403);
        $this->replyingToId = $commentId;
        $this->replyBody = '';
    }

    public function cancelReply(): void
    {
        $this->replyingToId = null;
        $this->replyBody = '';
    }

    public function postReply(): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()->can('comment.create'), 403);

        $this->validateOnly('replyBody');

        // Validate the parent actually belongs to this post
        $parent = Comment::where('post_id', $this->post->id)
            ->findOrFail($this->replyingToId);

        Comment::create([
            'post_id' => $this->post->id,
            'user_id' => auth()->id(),
            'parent_id' => $parent->id,
            'body' => $this->replyBody,
        ]);

        // Auto-expand the parent thread so the new reply is visible
        if (! in_array($parent->id, $this->expandedReplies)) {
            $this->expandedReplies[] = $parent->id;
        }

        $this->replyingToId = null;
        $this->replyBody = '';
        $this->dispatch('comment-posted');
    }

    // ─────────────────────────────────────────────────
    // Delete a comment
    // ─────────────────────────────────────────────────

    public function confirmDelete(int $commentId): void
    {
        $this->confirmDeleteId = $commentId;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deleteComment(): void
    {
        $comment = Comment::findOrFail($this->confirmDeleteId);
        Gate::authorize('delete', $comment);

        $comment->delete();
        $this->confirmDeleteId = null;
    }

    // ─────────────────────────────────────────────────
    // Toggle reply visibility
    // ─────────────────────────────────────────────────

    public function toggleReplies(int $commentId): void
    {
        if (in_array($commentId, $this->expandedReplies)) {
            $this->expandedReplies = array_diff($this->expandedReplies, [$commentId]);
        } else {
            $this->expandedReplies[] = $commentId;
        }
    }

    // ─────────────────────────────────────────────────
    // Computed
    // ─────────────────────────────────────────────────

    /**
     * Paginated top-level comments with their reply count and author.
     */
    #[Computed]
    public function comments()
    {
        return $this->post->comments()
            ->topLevel()
            ->with([
                'user:id,name',
                'replies' => fn ($q) => $q->with('user:id,name')->latest(),
            ])
            ->withCount('replies')
            ->latest()
            ->paginate(20);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.posts.comment-thread');
    }
}
