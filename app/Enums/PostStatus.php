<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /**
     * DaisyUI v5 badge colour class for each status.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-warning',
            self::Published => 'badge-success',
            self::Archived => 'badge-neutral',
        };
    }

    /**
     * Allowed transitions from this state.
     * Enforced in PostService::changeStatus() before persisting.
     */
    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Draft => in_array($new, [self::Published]),
            self::Published => in_array($new, [self::Archived, self::Draft]),
            self::Archived => in_array($new, [self::Published]),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
