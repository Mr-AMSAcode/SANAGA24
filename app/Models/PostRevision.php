<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostRevision extends Model
{
    /** @use HasFactory<\Database\Factories\PostRevisionFactory> */
    use HasFactory;

    /**
     * Immutable snapshot — only created_at, never updated.
     */
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'editor_id',
        'title',
        'content',
        'section',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
