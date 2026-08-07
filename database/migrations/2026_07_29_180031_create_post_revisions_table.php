<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable snapshots of a post's editable fields, taken right before
 * each save — so the history reads "what it was before this edit".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();

            // Whoever made the edit that prompted this snapshot. Nullable +
            // set null on delete: losing the editor account shouldn't erase
            // the post's history.
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->longText('content');
            $table->string('section');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
    }
};
