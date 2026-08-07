<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'age',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'age' => 'integer',
        ];
    }

    // ─────────────────────────────────────────────────
    // Blog Relationships
    // ─────────────────────────────────────────────────

    /**
     * Posts authored by this user (editor/admin only in practice,
     * enforced at the policy layer — not the DB layer).
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'editor_id');
    }

    /**
     * Comments written by this user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Likes given by this user (polymorphic target).
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    // ─────────────────────────────────────────────────
    // Role helpers — syntactic sugar over Spatie
    // ─────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isEditor(): bool
    {
        return $this->hasRole('editor');
    }

    public function isRegularUser(): bool
    {
        return $this->hasRole('user');
    }

    /**
     * True if this user can manage (edit/delete) a given post.
     * Mirrors PostPolicy::update() — useful in Blade / Livewire
     * components without injecting the Gate.
     */
    public function canManagePost(Post $post): bool
    {
        return $this->isAdmin() || $post->editor_id === $this->id;
    }

    // ─────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────

    /**
     * Get the user's initials (for avatars).
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn(string $word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
