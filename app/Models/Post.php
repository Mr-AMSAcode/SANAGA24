<?php

namespace App\Models;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'editor_id',
        'title',
        'slug',
        'content',
        'section',
        'status',
    ];

    /**
     * Cast attributes to their proper PHP types.
     * Using method form (PHP 8.4 compatible, Laravel 12).
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'section' => PostSection::class,
        ];
    }

    // ─────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────

    /**
     * The editor (User) who authored this post.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    /**
     * Pictures attached to this post.
     */
    public function pictures(): HasMany
    {
        return $this->hasMany(Picture::class);
    }

    /**
     * Top-level comments on this post (parent_id IS NULL).
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    /**
     * All comments on this post, including replies.
     */
    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    /**
     * Polymorphic likes targeting this post.
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'target');
    }

    /**
     * Denormalized stats for this post (1:1).
     */
    public function stats(): HasOne
    {
        return $this->hasOne(PostStats::class);
    }

    /**
     * Scheduling / lifecycle metadata (1:1).
     */
    public function postStatus(): HasOne
    {
        return $this->hasOne(PostStatus::class);
    }

    // ─────────────────────────────────────────────────
    // Query Scopes
    // ─────────────────────────────────────────────────

    /**
     * Only published posts — used on all public-facing queries.
     */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', PostStatus::Published);
    }

    /**
     * Filter by newspaper section.
     */
    public function scopeInSection(\Illuminate\Database\Eloquent\Builder $query, PostSection|string $section): void
    {
        $query->where('section', $section);
    }

    /**
     * Posts owned by a specific editor.
     */
    public function scopeByEditor(\Illuminate\Database\Eloquent\Builder $query, int $editorId): void
    {
        $query->where('editor_id', $editorId);
    }

    // ─────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────

    /**
     * Whether the authenticated user has liked this post.
     * Avoids N+1 when liked_by_auth is eager-loaded as a withCount/exists subquery.
     */
    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === PostStatus::Draft;
    }

    // ─────────────────────────────────────────────────
    // Slug auto-generation
    // ─────────────────────────────────────────────────

    /**
     * Automatically generate a unique slug when the title is set.
     * Called explicitly in PostService — not via an observer — to keep
     * business logic out of the model.
     */
    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();

        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }

    // ─────────────────────────────────────────────────
    // Route model binding key
    // ─────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
