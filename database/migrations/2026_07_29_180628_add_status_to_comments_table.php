<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft moderation: comments are visible immediately (status defaults to
 * 'approved', so nothing changes for existing behaviour/tests), but an
 * admin can now reject one to hide it from the public thread without
 * hard-deleting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->enum('status', ['approved', 'rejected'])->default('approved')->after('content');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
