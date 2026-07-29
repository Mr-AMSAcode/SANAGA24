<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Picture extends Model
{
    /** @use HasFactory<\Database\Factories\PictureFactory> */
    use HasFactory;

    public $timestamps = false; // only created_at — handled manually below

    protected $fillable = [
        'post_id',
        'url',
        'alt_text',
        'is_featured',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'created_at' => 'datetime',
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
    // Scopes
    // ─────────────────────────────────────────────────

    /**
     * The single featured/cover image for a post.
     */
    public function scopeFeatured(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('is_featured', true);
    }
}
