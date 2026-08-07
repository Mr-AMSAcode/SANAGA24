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

    case News = 'actualite';
    case Editorial = 'editorial';
    case CaBouge = 'ca-bouge';
    case Zoom = 'zoom';
    case LeDossier = 'le-dossier';
    case Communities = 'au-coeur-des-communautes';
    case Infrastructure = 'infrastructures';
    case Projects = 'projets';
    case ArtsAndCulture = 'arts-et-culture';
    case Tourism = 'tourisme';
    case Agribusiness = 'agroalimentaire';
    case AboutUs = 'qui-sommes-nous';

    public function label(): string
    {
        return match ($this) {
            self::Politics => 'Politics',
            self::Sports => 'Sports',
            self::Culture => 'Culture',
            self::Science => 'Science',
            self::Opinion => 'Opinion',
            self::World => 'World',
            self::News => 'News',
            self::Editorial => 'Editorial',
            self::CaBouge => "What's Moving",
            self::Zoom => 'Zoom',
            self::LeDossier => 'Special Report',
            self::Communities => 'At the Heart of Communities',
            self::Infrastructure => 'Infrastructure',
            self::Projects => 'Projects',
            self::ArtsAndCulture => 'Arts & Culture',
            self::Tourism => 'Tourism',
            self::Agribusiness => 'Agribusiness',
            self::AboutUs => 'About Us',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Top-level nav items, in display order. Home isn't a PostSection so
     * it isn't listed here — the nav adds it separately, first.
     */
    public static function primaryNav(): array
    {
        return [self::Politics, self::Sports, self::Culture, self::News];
    }

    /**
     * Sub-items shown inside the "Autre" nav dropdown.
     */
    public static function otherMenu(): array
    {
        return [
            self::Editorial,
            self::CaBouge,
            self::Zoom,
            self::LeDossier,
            self::Communities,
            self::Infrastructure,
            self::Projects,
            self::ArtsAndCulture,
            self::Tourism,
            self::Agribusiness,
            self::AboutUs,
        ];
    }

    /**
     * Every section a reader can actually navigate to from the menu —
     * primary nav + the "Autre" dropdown. Science/Opinion/World stay
     * valid (existing posts keep working, URLs keep resolving) but are
     * no longer part of the visible menu structure.
     */
    public static function visible(): array
    {
        return [...self::primaryNav(), ...self::otherMenu()];
    }
}
