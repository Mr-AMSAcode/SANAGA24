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
     * Videos attached to this post — either an uploaded file or a
     * YouTube/Vimeo embed (see Video::isUpload()/isEmbed()).
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * Tags/keywords attached to this post.
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Snapshots of this post's editable fields taken before each edit —
     * newest first.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest();
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
     *
     * Fully-qualified on purpose: this file imports the PostStatus *enum*
     * (App\Enums\PostStatus) for the `status` cast above, which would
     * otherwise shadow the PostStatus *model* (App\Models\PostStatus, same
     * namespace as this class) that this relation actually needs.
     */
    public function postStatus(): HasOne
    {
        return $this->hasOne(\App\Models\PostStatus::class);
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
     * Filter to posts carrying a given tag (by slug).
     */
    public function scopeWithTagSlug(\Illuminate\Database\Eloquent\Builder $query, string $slug): void
    {
        $query->whereHas('tags', fn ($q) => $q->where('slug', $slug));
    }

    /**
     * Posts owned by a specific editor.
     */
    public function scopeByEditor(\Illuminate\Database\Eloquent\Builder $query, int $editorId): void
    {
        $query->where('editor_id', $editorId);
    }

    /**
     * Full-text search across title (weighted higher) and content, via the
     * generated `search_vector` column (see the add_search_vector_to_posts
     * migration). websearch_to_tsquery understands search-engine syntax:
     * quoted phrases, "or", and "-exclude".
     */
    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, string $term): void
    {
        $query->whereRaw("search_vector @@ websearch_to_tsquery('english', ?)", [$term]);
    }

    /**
     * Order by full-text match quality — pair with scopeSearch() for the
     * default "best match first" sort.
     */
    public function scopeOrderByRelevance(\Illuminate\Database\Eloquent\Builder $query, string $term): void
    {
        $query->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('english', ?)) DESC", [$term]);
    }

    /**
     * Scheduled posts whose go-live time has arrived — picked up by the
     * posts:publish-scheduled command.
     */
    public function scopeDueForPublishing(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', PostStatus::Scheduled)
            ->whereHas('postStatus', fn ($q) => $q->where('active_period_start', '<=', now()));
    }

    /**
     * Published posts whose active window has expired — picked up by the
     * posts:archive-expired command.
     */
    public function scopeDueForArchiving(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereHas('postStatus', fn ($q) => $q->whereNotNull('active_period_end')->where('active_period_end', '<=', now()));
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

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Scheduled;
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
