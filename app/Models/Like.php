<?php

namespace App\Models;

use App\Enums\LikeTargetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Like extends Model
{
    /** @use HasFactory<\Database\Factories\LikeFactory> */
    use HasFactory;

    /**
     * No updated_at — likes are immutable once created.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'target_type',  // 'post' or 'comment'  — resolved via getMorphClass()
        'target_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────

    /**
     * The user who gave the like.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The polymorphic target — either a Post or a Comment.
     *
     * Eloquent maps 'App\Models\Post' → configured morph alias ('post')
     * when you register morphMaps in AppServiceProvider.
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    // ─────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────

    public function scopeForPosts(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('target_type', 'post');
    }

    public function scopeForComments(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('target_type', 'comment');
    }

    public function scopeByUser(\Illuminate\Database\Eloquent\Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
