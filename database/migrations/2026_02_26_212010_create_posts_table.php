<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command: php artisan make:migration create_posts_table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('editor_id')
                ->constrained('users')
                ->restrictOnDelete(); // prevent deleting a user who owns posts

            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');

            // PostgreSQL native enum — cleaner than a VARCHAR check constraint
            $table->enum('section', ['politics', 'sports', 'culture', 'science', 'opinion', 'world'])
                ->default('politics');

            $table->enum('status', ['draft', 'published', 'archived'])
                ->default('draft');

            $table->timestamps();
            $table->softDeletes(); // admin soft-delete; hard-delete only via tinker/admin

            // ── Indexes ────────────────────────────────────────────
            // Primary public query: published posts ordered by date
            $table->index(['status', 'created_at']);

            // Section browsing: published + section
            $table->index(['section', 'status', 'created_at']);

            // Editor dashboard: posts by author
            $table->index(['editor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
