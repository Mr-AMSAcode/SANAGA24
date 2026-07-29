<?php

namespace App\Enums;

enum PostSection: string
{
    case Politics = 'politics';
    case Sports = 'sports';
    case Culture = 'culture';
    case Science = 'science';
    case Opinion = 'opinion';
    case World = 'world';

    public function label(): string
    {
        return match ($this) {
            self::Politics => 'Politics',
            self::Sports => 'Sports',
            self::Culture => 'Culture',
            self::Science => 'Science',
            self::Opinion => 'Opinion',
            self::World => 'World',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
