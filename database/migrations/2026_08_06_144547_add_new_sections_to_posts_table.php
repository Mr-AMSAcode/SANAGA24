<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens posts.section to add "Actualité" and the 12 "Autre" submenu
 * categories from the nav restructure. The original six values are
 * untouched — Science/Opinion/World simply stop appearing in the nav
 * menu (see PostSection::visible()) while staying valid for any posts
 * already published under them.
 *
 * Laravel's enum() on Postgres compiles to a VARCHAR + CHECK constraint
 * (no native enum TYPE), so widening it means dropping and re-adding
 * that constraint — same technique as the earlier "scheduled" status
 * migration.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $sections = [
        'politics', 'sports', 'culture', 'science', 'opinion', 'world',
        'actualite', 'editorial', 'ca-bouge', 'zoom', 'le-dossier',
        'au-coeur-des-communautes', 'infrastructures', 'projets',
        'arts-et-culture', 'tourisme', 'agroalimentaire', 'qui-sommes-nous', 'galerie',
    ];

    public function up(): void
    {
        DB::statement('ALTER TABLE posts DROP CONSTRAINT posts_section_check');
        DB::statement('ALTER TABLE posts ADD CONSTRAINT posts_section_check CHECK ((section)::text = ANY (ARRAY['.
            $this->quotedList($this->sections).
            ']::text[]))');
    }

    public function down(): void
    {
        $original = ['politics', 'sports', 'culture', 'science', 'opinion', 'world'];

        DB::statement("UPDATE posts SET section = 'politics' WHERE section NOT IN ('".implode("','", $original)."')");
        DB::statement('ALTER TABLE posts DROP CONSTRAINT posts_section_check');
        DB::statement('ALTER TABLE posts ADD CONSTRAINT posts_section_check CHECK ((section)::text = ANY (ARRAY['.
            $this->quotedList($original).
            ']::text[]))');
    }

    private function quotedList(array $values): string
    {
        return implode(', ', array_map(fn (string $v) => "'{$v}'::character varying", $values));
    }
};
