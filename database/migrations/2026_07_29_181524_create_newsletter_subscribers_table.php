<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();

            // Linked opportunistically if a logged-in user subscribes —
            // the subscription itself never requires an account.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Unguessable token used in the one-click unsubscribe link.
            $table->string('unsubscribe_token', 64)->unique();

            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
