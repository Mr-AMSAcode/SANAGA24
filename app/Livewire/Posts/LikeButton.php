<?php

namespace App\Livewire\Posts;

use App\Events\PostLikeCountUpdated;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\RateLimiter;
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

        // Likes are one click, not a form — silently drop excess rapid
        // clicks rather than surfacing an error for something this low-stakes.
        $rateLimitKey = 'like-toggle:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $user = auth()->user();

        if ($this->liked) {
            // Fetched then deleted on the instance (not a query-builder mass
            // delete) so the Like model's deleted() hook fires and keeps
            // PostStats.like_count in sync.
            \App\Models\Like::where([
                'user_id' => $user->id,
                'target_type' => $this->targetType,
                'target_id' => $this->targetId,
            ])->first()?->delete();

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

        // Only posts get a live-updating count elsewhere on the page
        // (PostShow's header); comment likes don't need this today.
        if ($this->targetType === 'post') {
            PostLikeCountUpdated::dispatch($this->targetId, $this->count);
        }
    }

    /**
     * Live-refresh the count when another visitor likes/unlikes the same
     * target. $this->targetId isn't eligible for the #[On('echo:...')]
     * attribute's static channel-name parsing, so this uses the dynamic
     * getListeners() form instead.
     */
    public function getListeners(): array
    {
        if ($this->targetType !== 'post') {
            return [];
        }

        return [
            "echo:post.{$this->targetId},post.like-count-updated" => 'refreshLikeCount',
        ];
    }

    public function refreshLikeCount(array $event): void
    {
        $this->count = $event['likeCount'];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.posts.like-button');
    }
}
