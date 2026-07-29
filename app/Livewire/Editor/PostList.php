<?php

namespace App\Livewire\Editor;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('My Posts — Editor')]
class PostList extends Component
{
    use WithPagination;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'section', except: '')]
    public string $sectionFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    /**
     * Reset pagination whenever any filter changes.
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSectionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', Post::class);
    }

    /**
     * Toggle sort column; flip direction if already sorting by that column.
     */
    public function sort(string $column): void
    {
        $allowed = ['title', 'status', 'created_at', 'section'];

        if (! in_array($column, $allowed)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }

        $this->resetPage();
    }

    /**
     * Quick-publish a draft directly from the list view.
     */
    public function publish(int $postId): void
    {
        $post = Post::findOrFail($postId);
        Gate::authorize('update', $post);

        $post->update(['status' => PostStatus::Published]);
        $post->postStatus()->updateOrCreate(
            ['post_id' => $post->id],
            ['active_period_start' => now(), 'is_archived' => false]
        );

        session()->flash('success', "\"{$post->title}\" published.");
    }

    /**
     * Quick soft-delete from the list view.
     */
    public function delete(int $postId): void
    {
        $post = Post::findOrFail($postId);
        Gate::authorize('delete', $post);

        $post->delete();
        session()->flash('success', "\"{$post->title}\" moved to trash.");
    }

    // ─────────────────────────────────────────────────
    // Computed queries
    // ─────────────────────────────────────────────────

    #[Computed]
    public function posts()
    {
        return Post::query()
            ->byEditor(auth()->id())
            ->with(['stats:post_id,view_count,like_count,comment_count'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sectionFilter, fn ($q) => $q->where('section', $this->sectionFilter))
            ->when($this->search, fn ($q) => $q->where('title', 'ilike', "%{$this->search}%"))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(15);
    }

    #[Computed]
    public function statusCounts(): array
    {
        return Post::query()
            ->byEditor(auth()->id())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    #[Computed]
    public function sections(): array
    {
        return PostSection::cases();
    }

    #[Computed]
    public function statuses(): array
    {
        return PostStatus::cases();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.editor.post-list');
    }
}
