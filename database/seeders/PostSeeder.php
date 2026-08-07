<?php

namespace Database\Seeders;

use App\Enums\PostSection;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Picture;
use App\Models\Post;
use App\Models\PostStats;
use App\Models\PostStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds posts and all related records (pictures, stats, statuses,
 * comments, likes) in a realistic ratio.
 *
 * Run standalone:
 *   php artisan db:seed --class=PostSeeder
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        // ── Pull existing editors and users from the DB ───────────────────
        // DatabaseSeeder creates admin + editor accounts first,
        // so we always have at least one of each.
        $editors = User::role('editor')->orWhereHas('roles', fn($q) => $q->where('name', 'admin'))->get();
        $users   = User::role('user')->get();

        // If no plain users exist yet, create some for comments/likes.
        if ($users->isEmpty()) {
            $users = User::factory()->asUser()->count(20)->create();
        }

        // ── Seed per section ──────────────────────────────────────────────
        foreach (PostSection::cases() as $section) {
            $this->command->info("  Seeding section: {$section->label()}");

            // 10 published posts per section (plenty for every layout)
            Post::factory()
                ->count(10)
                ->published()
                ->inSection($section)
                ->for($editors->random(), 'editor')
                ->create()
                ->each(function (Post $post) use ($users) {

                    // ── Featured picture (always) ─────────────────────────
                    Picture::factory()
                        ->featured()
                        ->for($post)
                        ->create();

                    // ── 0–2 extra pictures ────────────────────────────────
                    Picture::factory()
                        ->count(rand(0, 2))
                        ->for($post)
                        ->create();

                    // ── PostStats (1:1, required for trending queries) ─────
                    // like_count/comment_count start at 0: the Comment/Like
                    // model hooks increment them for real below, as those
                    // rows are actually created, so the seeded counts stay
                    // consistent with the seeded rows. view_count keeps its
                    // random factory value for realistic "trending" demo data.
                    PostStats::factory()
                        ->state(['like_count' => 0, 'comment_count' => 0])
                        ->for($post)
                        ->create();

                    // ── PostStatus (1:1 scheduling record) ────────────────
                    PostStatus::factory()
                        ->for($post)
                        ->create();

                    // ── Comments (3–8 top-level) ──────────────────────────
                    $topLevelComments = Comment::factory()
                        ->count(rand(3, 8))
                        ->for($post)
                        ->for($users->random(), 'user')
                        ->create();

                    // ── Replies (0–3 per top-level comment) ───────────────
                    $topLevelComments->each(function (Comment $parent) use ($users) {
                        Comment::factory()
                            ->count(rand(0, 3))
                            ->replyTo($parent)
                            ->for($users->random(), 'user')
                            ->create();
                    });

                    // ── Post likes ────────────────────────────────────────
                    $likers = $users->random(rand(0, min(10, $users->count())));
                    foreach ($likers as $liker) {
                        Like::factory()
                            ->forPost($post)
                            ->for($liker, 'user')
                            ->create();
                    }
                });

            // 2 draft posts per section (editor dashboard needs them)
            Post::factory()
                ->count(2)
                ->inSection($section)
                ->for($editors->random(), 'editor')
                ->create()
                ->each(function (Post $post) {
                    // Drafts still get a stats row (created atomically in production)
                    PostStats::factory()->zeroed()->for($post)->create();
                    PostStatus::factory()->for($post)->create();
                });

            // 1 archived post per section
            Post::factory()
                ->count(1)
                ->archived()
                ->inSection($section)
                ->for($editors->random(), 'editor')
                ->create()
                ->each(function (Post $post) {
                    PostStats::factory()->for($post)->create();
                    PostStatus::factory()->archived()->for($post)->create();
                });
        }

        $total = Post::count();
        $this->command->info("  ✓ {$total} posts seeded across " . count(PostSection::cases()) . " sections.");
    }
}
