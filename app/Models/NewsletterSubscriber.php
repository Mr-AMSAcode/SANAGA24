<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    /** @use HasFactory<\Database\Factories\NewsletterSubscriberFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'user_id',
        'unsubscribe_token',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('unsubscribed_at');
    }

    public function isActive(): bool
    {
        return $this->unsubscribed_at === null;
    }

    /**
     * Subscribe an email address, reactivating an existing (previously
     * unsubscribed) row rather than erroring on the unique constraint.
     */
    public static function subscribe(string $email, ?User $user = null): self
    {
        $subscriber = static::firstOrNew(['email' => $email]);

        $subscriber->fill([
            'user_id' => $user?->id ?? $subscriber->user_id,
            'unsubscribe_token' => $subscriber->unsubscribe_token ?? Str::random(48),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ])->save();

        return $subscriber;
    }
}
