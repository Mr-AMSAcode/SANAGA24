<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostStatus extends Model
{
    /** @use HasFactory<\Database\Factories\PostStatusFactory> */
    use HasFactory;

    protected $table = 'post_statuses';

    protected $fillable = [
        'post_id',
        'active_period_start',
        'active_period_end',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'active_period_start' => 'datetime',
            'active_period_end' => 'datetime',
            'is_archived' => 'boolean',
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

    public function isCurrentlyActive(): bool
    {
        if ($this->is_archived) {
            return false;
        }

        $now = now();

        if ($this->active_period_end !== null && $now->isAfter($this->active_period_end)) {
            return false;
        }

        return $this->active_period_start !== null && $now->isAfter($this->active_period_start);
    }

    public function archive(): void
    {
        $this->update([
            'is_archived' => true,
            'active_period_end' => now(),
        ]);
    }
}
