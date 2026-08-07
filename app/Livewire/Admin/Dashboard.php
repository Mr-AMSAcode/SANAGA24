<?php

namespace App\Livewire\Admin;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Admin Dashboard — Sanaga24')]
class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('admin.panel.view'),
            403
        );
    }

    // ─────────────────────────────────────────────────
    // Site-wide aggregates
    // ─────────────────────────────────────────────────

    #[Computed]
    public function siteStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_editors' => User::role('editor')->count(),
            'total_admins' => User::role('admin')->count(),
            'total_posts' => Post::withTrashed()->count(),
            'published' => Post::where('status', PostStatus::Published)->count(),
            'drafts' => Post::where('status', PostStatus::Draft)->count(),
            'scheduled' => Post::where('status', PostStatus::Scheduled)->count(),
            'archived' => Post::where('status', PostStatus::Archived)->count(),
            'total_views' => DB::table('post_stats')->sum('view_count'),
            'total_likes' => DB::table('post_stats')->sum('like_count'),
            'total_comments' => DB::table('post_stats')->sum('comment_count'),
        ];
    }

    /**
     * Posts published per day for the last 30 days — used for the chart.
     * Returns an array of ['date' => 'YYYY-MM-DD', 'count' => N].
     */
    #[Computed]
    public function publishingTrend(): array
    {
        return Post::query()
            ->where('status', PostStatus::Published)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => $row->count])
            ->toArray();
    }

    /**
     * Top 5 most active editors by published post count.
     */
    #[Computed]
    public function topEditors()
    {
        return User::query()
            ->role('editor')
            ->withCount(['posts as published_count' => fn ($q) => $q->published()])
            ->orderByDesc('published_count')
            ->limit(5)
            ->get(['id', 'name', 'email']);
    }

    /**
     * 5 most recent posts across all editors.
     */
    #[Computed]
    public function recentPosts()
    {
        return Post::with('editor:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'slug', 'status', 'editor_id', 'created_at']);
    }

    /**
     * 5 most recently registered users.
     */
    #[Computed]
    public function recentUsers()
    {
        return User::with('roles:name')
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'email', 'created_at']);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.dashboard');
    }
}
