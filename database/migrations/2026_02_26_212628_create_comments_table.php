<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command: php artisan make:migration create_comments_table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete(); // deleting a post removes all its comments

            // Self-referencing FK for threaded replies.
            // Nullable = top-level comment. Non-null = reply.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('comments')
                ->nullOnDelete(); // if parent deleted, replies become top-level (not cascade)

            $table->text('content');
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────────────────────
            // Loading all top-level comments for a post
            $table->index(['post_id', 'parent_id', 'created_at']);

            // Loading replies to a specific comment
            $table->index(['parent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
