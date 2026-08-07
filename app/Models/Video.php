<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    /** @use HasFactory<\Database\Factories\VideoFactory> */
    use HasFactory;

    public $timestamps = false; // only created_at — handled manually below

    protected $fillable = [
        'post_id',
        'type',
        'url',
        'provider',
        'title',
        'file_size',
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

    public function isEmbed(): bool
    {
        return $this->type === 'embed';
    }

    public function isUpload(): bool
    {
        return $this->type === 'upload';
    }
}
