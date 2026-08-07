<?php

namespace App\Livewire\Editor;

use App\Enums\PostStatus;
use App\Models\Post;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Editor Dashboard — Sanaga24')]
class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('editor.panel.view'),
            403
        );
    }

    // ─────────────────────────────────────────────────
    // Aggregated stats for the authenticated editor
    // ─────────────────────────────────────────────────

    #[Computed]
    public function stats(): array
    {
        $editorId = auth()->id();

        $posts = Post::byEditor($editorId)
            ->with('stats')
            ->withTrashed()         // include soft-deleted for honest totals
            ->get();

        $published = $posts->where('status', PostStatus::Published);

        return [
            'total_posts' => $posts->count(),
            'published' => $published->count(),
            'drafts' => $posts->where('status', PostStatus::Draft)->count(),
            'scheduled' => $posts->where('status', PostStatus::Scheduled)->count(),
            'archived' => $posts->where('status', PostStatus::Archived)->count(),
            'trashed' => $posts->whereNotNull('deleted_at')->count(),
            'total_views' => $posts->sum(fn ($p) => $p->stats?->view_count ?? 0),
            'total_likes' => $posts->sum(fn ($p) => $p->stats?->like_count ?? 0),
            'total_comments' => $posts->sum(fn ($p) => $p->stats?->comment_count ?? 0),
        ];
    }

    /**
     * Top 5 most viewed published posts by this editor.
     */
    #[Computed]
    public function topPosts()
    {
        return Post::query()
            ->byEditor(auth()->id())
            ->published()
            ->with('stats')
            ->join('post_stats', 'posts.id', '=', 'post_stats.post_id')
            ->orderByDesc('post_stats.view_count')
            ->select('posts.*')
            ->limit(5)
            ->get();
    }

    /**
     * The 5 most recent posts regardless of status.
     */
    #[Computed]
    public function recentPosts()
    {
        return Post::query()
            ->byEditor(auth()->id())
            ->with(['stats:post_id,view_count'])
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.editor.dashboard');
    }
}
