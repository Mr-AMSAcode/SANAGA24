<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'post_id',
        'parent_id',
        'content',
    ];

    // ─────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────

    /**
     * The user who wrote this comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The post this comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The parent comment (if this is a reply). Null for top-level comments.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Direct replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->latest();
    }

    /**
     * Polymorphic likes on this comment.
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'target');
    }

    // ─────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────

    /**
     * Only top-level comments (not replies).
     */
    public function scopeTopLevel(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Only replies (has a parent).
     */
    public function scopeReplies(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNotNull('parent_id');
    }

    // ─────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
