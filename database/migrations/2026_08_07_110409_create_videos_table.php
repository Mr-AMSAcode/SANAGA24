<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A post can carry videos two ways:
 *  - 'embed'  — a YouTube/Vimeo URL, rendered as an iframe. No file ever
 *               touches our storage or bandwidth.
 *  - 'upload' — a video file stored on our own disk, served directly via
 *               a plain <video> tag. No transcoding/adaptive streaming —
 *               the file is served exactly as uploaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')
                ->nullable() // nullable: videos can be uploaded before the post is saved
                ->constrained('posts')
                ->nullOnDelete();

            $table->enum('type', ['upload', 'embed']);

            // 'upload' → storage URL of the file; 'embed' → normalized
            // player URL (e.g. https://www.youtube.com/embed/{id}).
            $table->string('url');

            // 'youtube' | 'vimeo' for embeds; null for uploads.
            $table->string('provider')->nullable();

            $table->string('title')->nullable();

            // Bytes — only set for uploads, informational.
            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
