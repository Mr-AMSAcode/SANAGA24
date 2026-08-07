<?php

namespace App\Models;

use App\Enums\CommentStatus;
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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }

    /**
     * Keep the denormalized PostStats.comment_count in sync.
     * SoftDeletes fires the "deleted" event on soft-delete too, which is
     * what we want — a trashed comment shouldn't count.
     */
    protected static function booted(): void
    {
        static::created(function (self $comment): void {
            PostStats::where('post_id', $comment->post_id)->increment('comment_count');
        });

        static::deleted(function (self $comment): void {
            PostStats::where('post_id', $comment->post_id)->decrement('comment_count');
        });
    }

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

    /**
     * Only comments an admin hasn't rejected — what the public thread
     * shows. Not baked into the base relations so the admin moderation
     * queue can still see everything.
     */
    public function scopeApproved(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
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

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::Approved;
    }
}
