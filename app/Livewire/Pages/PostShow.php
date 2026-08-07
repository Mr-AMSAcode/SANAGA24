<?php

namespace App\Livewire\Pages;

use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Sanaga24 — Post')]
class PostShow extends Component
{
    #[Locked]
    public Post $post;

    /**
     * Optimistic like state — updated immediately before DB round-trip.
     */
    public bool $liked    = false;
    public int  $likeCount = 0;

    public function mount(Post $post): void
    {
        // Show only published posts publicly; editors/admins may preview drafts
        if ($post->status->value !== 'published') {
            Gate::authorize('update', $post);
        }

        $this->post      = $post->load(['editor:id,name', 'pictures', 'videos', 'stats', 'tags']);
        $this->likeCount = $post->stats?->like_count ?? $post->likes()->count();

        if (auth()->check()) {
            $this->liked = $post->likes()
                ->where('user_id', auth()->id())
                ->exists();
        }

        // Increment view count asynchronously (best-effort)
        $this->incrementViewCount();
    }

    private function incrementViewCount(): void
    {
        try {
            // updateOrCreate() with a raw "view_count + 1" expression only
            // works for the UPDATE path — as an INSERT value it references
            // a column that doesn't exist yet. firstOrCreate() + increment()
            // handles both the existing-row and no-row-yet cases correctly.
            $this->post->stats()
                ->firstOrCreate(['post_id' => $this->post->id])
                ->increment('view_count');
        } catch (\Throwable) {
            // Non-critical — never break the page for this
        }
    }

    /**
     * Refresh the comment count badge — the Comment model itself keeps
     * PostStats.comment_count in sync on create/delete, this just re-pulls
     * the current value for display.
     */
    #[On('comment-count-changed')]
    public function refreshCommentCount(): void
    {
        $this->post->refresh();
    }

    // ─────────────────────────────────────────────────
    // Sidebar: related posts (same section)
    // ─────────────────────────────────────────────────

    #[Computed]
    public function relatedPosts()
    {
        return Post::query()
            ->published()
            ->inSection($this->post->section->value)
            ->where('id', '!=', $this->post->id)
            ->with(['pictures', 'editor:id,name'])
            ->latest()
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function latestPosts()
    {
        return Post::query()
            ->published()
            ->where('id', '!=', $this->post->id)
            ->with(['pictures', 'editor:id,name'])
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.post-show');
    }
}
