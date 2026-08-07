<?php

namespace App\Enums;

enum CommentStatus: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Approved => 'badge-success',
            self::Rejected => 'badge-error',
        };
    }
}
