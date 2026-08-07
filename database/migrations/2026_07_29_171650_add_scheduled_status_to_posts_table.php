<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'scheduled' to the posts.status check constraint so a post can be
 * queued to go live at a future active_period_start (see post_statuses)
 * without being a plain, unscheduled draft.
 *
 * Laravel's enum() on Postgres compiles to a VARCHAR + CHECK constraint
 * (there's no native enum TYPE involved), so widening it means dropping
 * and re-adding that constraint — there's no ALTER TYPE ... ADD VALUE here.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE posts DROP CONSTRAINT posts_status_check');
        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_status_check CHECK (status::text = ANY (ARRAY['draft', 'scheduled', 'published', 'archived']::text[]))");
    }

    public function down(): void
    {
        // Any post left in 'scheduled' can't satisfy the narrower constraint.
        DB::statement("UPDATE posts SET status = 'draft' WHERE status = 'scheduled'");
        DB::statement('ALTER TABLE posts DROP CONSTRAINT posts_status_check');
        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_status_check CHECK (status::text = ANY (ARRAY['draft', 'published', 'archived']::text[]))");
    }
};
