<?php

namespace App\Livewire\Admin;

use App\Enums\CommentStatus;
use App\Models\Comment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Comment Moderation — Admin')]
class CommentList extends Component
{
    use WithPagination;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin.panel.view'), 403);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function approve(int $commentId): void
    {
        $comment = Comment::findOrFail($commentId);
        Gate::authorize('delete', $comment); // admin-only ability, reused for the bypass
        $comment->update(['status' => CommentStatus::Approved]);
        session()->flash('success', 'Comment approved.');
    }

    public function reject(int $commentId): void
    {
        $comment = Comment::findOrFail($commentId);
        Gate::authorize('delete', $comment);
        $comment->update(['status' => CommentStatus::Rejected]);
        session()->flash('success', 'Comment rejected and hidden from the public thread.');
    }

    public function delete(int $commentId): void
    {
        $comment = Comment::findOrFail($commentId);
        Gate::authorize('delete', $comment);
        $comment->delete();
        session()->flash('success', 'Comment permanently removed.');
    }

    #[Computed]
    public function comments()
    {
        return Comment::query()
            ->with(['user:id,name', 'post:id,title,slug'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where('content', 'ilike', "%{$this->search}%"))
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function statuses(): array
    {
        return CommentStatus::cases();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.comment-list');
    }
}
