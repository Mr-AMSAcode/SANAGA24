<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Galerie" turned out not to be an article rubrique at all — it's the
 * site-wide media gallery (all images across every post), a dedicated
 * page, not a Post category. Removing it from PostSection means removing
 * it from the check constraint too, or a post could still get created
 * with a section value with no corresponding enum case.
 *
 * No live data risk: no post was ever actually published under 'galerie'
 * (only a throwaway test post, created and deleted the same session).
 */
return new class extends Migration
{
    private array $withGalerie = [
        'politics', 'sports', 'culture', 'science', 'opinion', 'world',
        'actualite', 'editorial', 'ca-bouge', 'zoom', 'le-dossier',
        'au-coeur-des-communautes', 'infrastructures', 'projets',
        'arts-et-culture', 'tourisme', 'agroalimentaire', 'qui-sommes-nous', 'galerie',
    ];

    private array $withoutGalerie = [
        'politics', 'sports', 'culture', 'science', 'opinion', 'world',
        'actualite', 'editorial', 'ca-bouge', 'zoom', 'le-dossier',
        'au-coeur-des-communautes', 'infrastructures', 'projets',
        'arts-et-culture', 'tourisme', 'agroalimentaire', 'qui-sommes-nous',
    ];

    public function up(): void
    {
        DB::statement("UPDATE posts SET section = 'politics' WHERE section = 'galerie'");
        DB::statement('ALTER TABLE posts DROP CONSTRAINT posts_section_check');
        DB::statement('ALTER TABLE posts ADD CONSTRAINT posts_section_check CHECK ((section)::text = ANY (ARRAY['.
            $this->quotedList($this->withoutGalerie).
            ']::text[]))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE posts DROP CONSTRAINT posts_section_check');
        DB::statement('ALTER TABLE posts ADD CONSTRAINT posts_section_check CHECK ((section)::text = ANY (ARRAY['.
            $this->quotedList($this->withGalerie).
            ']::text[]))');
    }

    private function quotedList(array $values): string
    {
        return implode(', ', array_map(fn (string $v) => "'{$v}'::character varying", $values));
    }
};
