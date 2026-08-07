<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostStats extends Model
{
    /** @use HasFactory<\Database\Factories\PostStatsFactory> */
    use HasFactory;

    /**
     * No created_at — stats record is created with the post and only ever updated.
     */
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'view_count',
        'like_count',
        'comment_count',
        'updated_at',
    ];

    /**
     * Mirrors the DB column defaults on the in-memory model, so a freshly
     * created instance reads as 0 immediately without a round-trip refresh.
     */
    protected $attributes = [
        'view_count' => 0,
        'like_count' => 0,
        'comment_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    // ─────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────

    public function incrementViews(): void
    {
        $this->increment('view_count');
        $this->updated_at = now();
        $this->save();
    }

    /**
     * Recompute like_count/comment_count from source data.
     * Comment and Like models keep these in sync automatically on
     * create/delete — this is only a manual reconciliation helper.
     */
    public function recalculate(): void
    {
        $post = $this->post()->with('allComments', 'likes')->first();

        $this->update([
            'like_count' => $post->likes()->count(),
            'comment_count' => $post->allComments()->count(),
            'updated_at' => now(),
        ]);
    }
}
