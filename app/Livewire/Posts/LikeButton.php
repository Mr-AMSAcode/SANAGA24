<?php

namespace App\Livewire\Posts;

use App\Models\Comment;
use App\Models\Post;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Reusable polymorphic like/unlike button.
 *
 * Usage in any Blade template:
 *   <livewire:posts.like-button :target="$post" />
 *   <livewire:posts.like-button :target="$comment" />
 *
 * The component accepts any model that has a morphMany 'likes' relationship
 * registered in the morph map (Post or Comment).
 */
class LikeButton extends Component
{
    /**
     * The model being liked: a Post or Comment instance.
     * #[Locked] ensures the client cannot swap it for another model.
     */
    #[Locked]
    public string $targetType;    // 'post' | 'comment'

    #[Locked]
    public int $targetId;

    /**
     * Whether the currently authenticated user has liked this target.
     */
    public bool $liked = false;

    /**
     * Displayed count — updated optimistically.
     */
    public int $count = 0;

    public function mount(Post|Comment $target): void
    {
        // Resolve the morph alias ('post' or 'comment') from the model class
        $this->targetType = array_search(get_class($target), \Illuminate\Database\Eloquent\Relations\Relation::morphMap());
        $this->targetId = $target->id;
        $this->count = $target->likes()->count();

        if (auth()->check()) {
            $this->liked = $target->likes()
                ->where('user_id', auth()->id())
                ->exists();
        }
    }

    /**
     * Toggle the like. Uses optimistic UI: state changes immediately
     * on the client side via Livewire's wire:click, then persists.
     */
    public function toggle(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'));

            return;
        }

        $user = auth()->user();

        if ($this->liked) {
            \App\Models\Like::where([
                'user_id' => $user->id,
                'target_type' => $this->targetType,
                'target_id' => $this->targetId,
            ])->delete();

            $this->liked = false;
            $this->count = max(0, $this->count - 1);
        } else {
            \App\Models\Like::create([
                'user_id' => $user->id,
                'target_type' => $this->targetType,
                'target_id' => $this->targetId,
                'created_at' => now(),
            ]);

            $this->liked = true;
            $this->count++;
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.posts.like-button');
    }
}
