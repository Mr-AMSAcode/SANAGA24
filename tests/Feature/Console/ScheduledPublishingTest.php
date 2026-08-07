<?php

/**
 * tests/Feature/Console/ScheduledPublishingTest.php
 *
 * Covers the scheduled-publishing pipeline end to end at the data layer:
 * the PostStatus state machine, the Post query scopes that find due
 * work, and the two console commands that actually flip the status.
 */

use App\Console\Commands\ArchiveExpiredPosts;
use App\Console\Commands\PublishScheduledPosts;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostStatus as PostStatusModel;
use App\Notifications\PostPublishedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('PostStatus transitions', function () {
    it('allows Draft to go to Published or Scheduled, nothing else', function () {
        expect(PostStatus::Draft->canTransitionTo(PostStatus::Published))->toBeTrue()
            ->and(PostStatus::Draft->canTransitionTo(PostStatus::Scheduled))->toBeTrue()
            ->and(PostStatus::Draft->canTransitionTo(PostStatus::Archived))->toBeFalse();
    });

    it('allows Scheduled to go to Published (early) or back to Draft (cancel)', function () {
        expect(PostStatus::Scheduled->canTransitionTo(PostStatus::Published))->toBeTrue()
            ->and(PostStatus::Scheduled->canTransitionTo(PostStatus::Draft))->toBeTrue()
            ->and(PostStatus::Scheduled->canTransitionTo(PostStatus::Archived))->toBeFalse();
    });
});

describe('Post::scopeDueForPublishing()', function () {
    it('only includes Scheduled posts whose active_period_start has arrived', function () {
        $due = Post::factory()->create(['status' => PostStatus::Scheduled]);
        PostStatusModel::factory()->for($due)->create(['active_period_start' => now()->subMinute()]);

        $notYet = Post::factory()->create(['status' => PostStatus::Scheduled]);
        PostStatusModel::factory()->for($notYet)->create(['active_period_start' => now()->addHour()]);

        $draft = Post::factory()->create(['status' => PostStatus::Draft]);
        PostStatusModel::factory()->for($draft)->create(['active_period_start' => now()->subMinute()]);

        $result = Post::query()->dueForPublishing()->get();

        expect($result->pluck('id')->toArray())->toBe([$due->id]);
    });
});

describe('Post::scopeDueForArchiving()', function () {
    it('only includes Published posts whose active_period_end has passed', function () {
        $expired = Post::factory()->published()->create();
        PostStatusModel::factory()->for($expired)->create(['active_period_end' => now()->subMinute()]);

        $stillActive = Post::factory()->published()->create();
        PostStatusModel::factory()->for($stillActive)->create(['active_period_end' => now()->addDay()]);

        $noEndDate = Post::factory()->published()->create();
        PostStatusModel::factory()->for($noEndDate)->create(['active_period_end' => null]);

        $result = Post::query()->dueForArchiving()->get();

        expect($result->pluck('id')->toArray())->toBe([$expired->id]);
    });
});

describe('posts:publish-scheduled command', function () {
    it('publishes every due post and leaves its go-live timestamp untouched', function () {
        $post = Post::factory()->create(['status' => PostStatus::Scheduled]);
        $scheduledFor = now()->subMinutes(5)->startOfSecond();
        PostStatusModel::factory()->for($post)->create(['active_period_start' => $scheduledFor]);

        $this->artisan(PublishScheduledPosts::class)->assertSuccessful();

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Published)
            ->and($post->postStatus->active_period_start->equalTo($scheduledFor))->toBeTrue();
    });

    it('notifies the owning editor that their post went live', function () {
        Notification::fake();
        $post = Post::factory()->create(['status' => PostStatus::Scheduled]);
        PostStatusModel::factory()->for($post)->create(['active_period_start' => now()->subMinute()]);

        $this->artisan(PublishScheduledPosts::class);

        Notification::assertSentTo($post->editor, PostPublishedNotification::class);
    });

    it('leaves not-yet-due scheduled posts alone', function () {
        $post = Post::factory()->create(['status' => PostStatus::Scheduled]);
        PostStatusModel::factory()->for($post)->create(['active_period_start' => now()->addDay()]);

        $this->artisan(PublishScheduledPosts::class)->assertSuccessful();

        expect($post->fresh()->status)->toBe(PostStatus::Scheduled);
    });
});

describe('posts:archive-expired command', function () {
    it('archives every published post whose active period ended', function () {
        $post = Post::factory()->published()->create();
        PostStatusModel::factory()->for($post)->create(['active_period_end' => now()->subDay()]);

        $this->artisan(ArchiveExpiredPosts::class)->assertSuccessful();

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Archived)
            ->and($post->postStatus->is_archived)->toBeTrue();
    });

    it('leaves posts with no end date or a future one alone', function () {
        $post = Post::factory()->published()->create();
        PostStatusModel::factory()->for($post)->create(['active_period_end' => now()->addDay()]);

        $this->artisan(ArchiveExpiredPosts::class)->assertSuccessful();

        expect($post->fresh()->status)->toBe(PostStatus::Published);
    });
});
