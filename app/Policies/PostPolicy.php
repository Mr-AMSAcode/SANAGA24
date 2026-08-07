<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Register in AuthServiceProvider (or Laravel 12 auto-discovery):
 *
 *   protected $policies = [
 *       Post::class => PostPolicy::class,
 *   ];
 *
 * Usage:
 *   $this->authorize('update', $post);     // in Livewire component or Controller
 *   Gate::authorize('update', $post);       // in Service class
 *
 *   @can('update', $post) ... @endcan       // in Blade
 */
class PostPolicy
{
    use HandlesAuthorization;

    /**
     * Admins bypass all policy checks automatically.
     * This before() hook runs before every method below.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true; // admin can do everything
        }

        return null; // fall through to specific method
    }

    /**
     * Can the user VIEW a post?
     * Published posts are public (even unauthenticated).
     * Draft/archived: only the author or admin.
     */
    public function view(?User $user, Post $post): bool
    {
        if ($post->isPublished()) {
            return true;
        }

        // Draft / Archived: only the owning editor (or admin via before())
        return $user !== null && $post->editor_id === $user->id;
    }

    /**
     * Can the user view their own post list (the editor dashboard's
     * "My Posts" page)? Same permission as creating — anyone who can
     * write posts can see their own list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('post.create');
    }

    /**
     * Can the user CREATE a new post?
     * Requires the 'post.create' permission (editors + admins have it).
     */
    public function create(User $user): bool
    {
        return $user->can('post.create');
    }

    /**
     * Can the user EDIT a post?
     * Editors can only edit their own posts.
     */
    public function update(User $user, Post $post): bool
    {
        return $user->can('post.edit.own') && $post->editor_id === $user->id;
    }

    /**
     * Can the user PUBLISH their own draft post?
     */
    public function publish(User $user, Post $post): bool
    {
        return $user->can('post.publish.own') && $post->editor_id === $user->id;
    }

    /**
     * Can the user SOFT-DELETE a post?
     */
    public function delete(User $user, Post $post): bool
    {
        return $user->can('post.delete.own') && $post->editor_id === $user->id;
    }

    /**
     * Can the user RESTORE a soft-deleted post?
     * Only admins (handled by before() hook).
     */
    public function restore(User $user, Post $post): bool
    {
        return false; // only admin — before() handles it
    }

    /**
     * Can the user HARD-DELETE a soft-deleted post?
     * Only admins (handled by before() hook).
     */
    public function forceDelete(User $user, Post $post): bool
    {
        return false; // only admin — before() handles it
    }
}
