<?php

namespace App\Livewire\Admin;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('All Posts — Admin')]
class PostList extends Component
{
    use WithPagination;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'section', except: '')]
    public string $sectionFilter = '';

    #[Url(as: 'editor', except: '')]
    public string $editorFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    /**
     * IDs of posts selected via checkboxes for bulk actions.
     */
    public array $selected = [];

    /**
     * Whether to include soft-deleted (trashed) posts in results.
     */
    public bool $showTrashed = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin.panel.view'), 403);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSectionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEditorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────
    // Single-row actions
    // ─────────────────────────────────────────────────

    public function delete(int $postId): void
    {
        $post = Post::findOrFail($postId);
        Gate::authorize('delete', $post);
        $post->delete();
        session()->flash('success', "\"{$post->title}\" moved to trash.");
    }

    public function restore(int $postId): void
    {
        $post = Post::withTrashed()->findOrFail($postId);
        Gate::authorize('restore', $post);
        $post->restore();
        session()->flash('success', "\"{$post->title}\" restored.");
    }

    public function forceDelete(int $postId): void
    {
        $post = Post::withTrashed()->findOrFail($postId);
        Gate::authorize('forceDelete', $post);
        $post->forceDelete();
        session()->flash('success', 'Post permanently deleted.');
    }

    // ─────────────────────────────────────────────────
    // Bulk actions
    // ─────────────────────────────────────────────────

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()?->can('post.delete.any'), 403);
        $count = count($this->selected);
        Post::whereIn('id', $this->selected)->delete();
        $this->selected = [];
        session()->flash('success', "{$count} posts moved to trash.");
    }

    public function bulkPublish(): void
    {
        abort_unless(auth()->user()?->can('post.edit.any'), 403);
        Post::whereIn('id', $this->selected)
            ->where('status', PostStatus::Draft)
            ->update(['status' => PostStatus::Published]);
        $this->selected = [];
        session()->flash('success', 'Selected drafts published.');
    }

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

    // ─────────────────────────────────────────────────
    // Computed queries
    // ─────────────────────────────────────────────────

    #[Computed]
    public function posts()
    {
        return Post::query()
            ->when($this->showTrashed, fn ($q) => $q->withTrashed())
            ->with(['editor:id,name', 'stats:post_id,view_count,like_count,comment_count'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sectionFilter, fn ($q) => $q->where('section', $this->sectionFilter))
            ->when($this->editorFilter, fn ($q) => $q->where('editor_id', $this->editorFilter))
            ->when($this->search, fn ($q) => $q->where('title', 'ilike', "%{$this->search}%"))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);
    }

    /**
     * All editors for the filter dropdown.
     */
    #[Computed]
    public function editors()
    {
        return User::role('editor')
            ->orderBy('name')
            ->get(['id', 'name']);
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
        return view('livewire.admin.post-list');
    }
}
