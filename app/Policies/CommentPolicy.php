<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return null;
    }

    public function create(User $user): bool
    {
        return $user->can('comment.create');
    }

    /**
     * A user can delete their own comment.
     * Admins can delete any (handled by before()).
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->can('comment.delete.own') && $comment->user_id === $user->id;
    }
}
