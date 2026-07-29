<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Command: php artisan make:migration create_pictures_table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pictures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')
                ->nullable()      // nullable: pictures can be uploaded before post is saved
                ->constrained('posts')
                ->nullOnDelete(); // if post is deleted, disassociate picture (clean up via job)

            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->boolean('is_featured')->default(false);

            $table->timestamp('created_at')->useCurrent();

            // Only one featured image per post
            // $table->unique(['post_id', 'is_featured'], 'pictures_post_featured_unique');

            $table->index('post_id');
        });
        DB::statement('
            CREATE UNIQUE INDEX pictures_one_featured_per_post
            ON pictures (post_id)
            WHERE is_featured = true
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('pictures');
    }
};
