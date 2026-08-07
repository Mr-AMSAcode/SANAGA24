<?php

namespace App\Livewire\Pages;

use App\Models\Post;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Author — Sanaga24')]
class AuthorShow extends Component
{
    use WithPagination;

    #[Locked]
    public User $author;

    public function mount(User $author): void
    {
        // Only real bylines get a public page — a reader account with no
        // published posts has nothing to show here.
        abort_unless(
            Post::published()->byEditor($author->id)->exists(),
            404
        );

        $this->author = $author;
    }

    #[Computed]
    public function posts()
    {
        return Post::query()
            ->published()
            ->byEditor($this->author->id)
            ->with(['pictures' => fn ($q) => $q->featured(), 'stats:post_id,view_count,like_count,comment_count'])
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function publishedCount(): int
    {
        return Post::published()->byEditor($this->author->id)->count();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.author-show');
    }
}
