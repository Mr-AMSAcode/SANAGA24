<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command: php artisan make:migration create_likes_table
 *
 * Polymorphic: target_type IN ('post','comment'), target_id = PK of target.
 * No DB-level FK on (target_type, target_id) because PostgreSQL cannot enforce
 * polymorphic FKs. Integrity is enforced at the application layer (LikeService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 'post' or 'comment' — kept as varchar intentionally for flexibility.
            // A CHECK constraint enforces the allowed values at DB level.
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');

            $table->timestamp('created_at')->useCurrent();

            // ── Core constraint: one like per user per target ──────
            $table->unique(['user_id', 'target_type', 'target_id'], 'likes_user_target_unique');

            // ── Indexes ────────────────────────────────────────────
            // Count likes for a specific post or comment
            $table->index(['target_type', 'target_id']);

            // All likes by a user (profile page)
            $table->index(['user_id', 'target_type']);
        });

        // CHECK constraint: only allow 'post' or 'comment' as target_type
        // Uses raw DB statement — Schema builder doesn't expose CHECK directly.
        \DB::statement("ALTER TABLE likes ADD CONSTRAINT likes_target_type_check
            CHECK (target_type IN ('post', 'comment'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
