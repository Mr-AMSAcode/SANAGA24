<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Real full-text search, replacing the `title ilike '%...%'` scan.
 *
 * search_vector is a STORED GENERATED column: Postgres recomputes it
 * automatically on every insert/update, so it can never drift out of
 * sync with title/content — no application code or trigger needed.
 * Title is weighted 'A' (highest), content 'B', so title matches rank
 * above body matches. Indexed with GIN for fast @@ lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE posts ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(content, '')), 'B')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX posts_search_vector_index ON posts USING GIN (search_vector)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS posts_search_vector_index');
        DB::statement('ALTER TABLE posts DROP COLUMN IF EXISTS search_vector');
    }
};
