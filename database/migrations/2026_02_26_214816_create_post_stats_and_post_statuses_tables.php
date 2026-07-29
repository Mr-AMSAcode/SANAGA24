<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command: php artisan make:migration create_post_stats_and_post_statuses_tables
 *
 * Two tables in one migration because they are always created and dropped together
 * and both depend on the posts table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── post_stats ─────────────────────────────────────────────────────
        // Denormalized counters cache. One record per post, created atomically
        // with the post inside PostService (DB transaction).
        Schema::create('post_stats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')
                ->unique()             // enforces the 1:1
                ->constrained('posts')
                ->cascadeOnDelete();

            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);

            // No created_at — created with the post.
            // updated_at lets you detect stale stats.
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // ── post_statuses ──────────────────────────────────────────────────
        // Temporal scheduling / lifecycle metadata. Separate from posts.status
        // (which holds the logical state). This holds the active window dates.
        Schema::create('post_statuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')
                ->unique()             // enforces the 1:1
                ->constrained('posts')
                ->cascadeOnDelete();

            $table->timestamp('active_period_start')->nullable();
            $table->timestamp('active_period_end')->nullable();
            $table->boolean('is_archived')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_statuses');
        Schema::dropIfExists('post_stats');
    }
};
