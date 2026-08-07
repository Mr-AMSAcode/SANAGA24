<?php

/**
 * tests/Feature/Livewire/Pages/HomeTest.php
 *
 * Covers App\Livewire\Pages\Home: the public homepage's hero carousel,
 * trending ticker, recent section and per-section blocks — including
 * that each only ever surfaces published content.
 */

use App\Enums\PostSection;
use App\Livewire\Pages\Home;
use App\Models\Picture;
use App\Models\Post;
use App\Models\PostStats;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('renders for guests', function () {
    Livewire::test(Home::class)->assertOk();
});

describe('heroPosts()', function () {
    it('only includes published posts that have at least one picture', function () {
        $withPicture = Post::factory()->published()->create();
        Picture::factory()->for($withPicture)->create();

        $withoutPicture = Post::factory()->published()->create();
        $draftWithPicture = Post::factory()->create();
        Picture::factory()->for($draftWithPicture)->create();

        $hero = Livewire::test(Home::class)->instance()->heroPosts();

        expect($hero->pluck('id')->toArray())->toBe([$withPicture->id])
            ->and($hero->pluck('id'))->not->toContain($withoutPicture->id)
            ->and($hero->pluck('id'))->not->toContain($draftWithPicture->id);
    });

    it('is capped at 3 posts', function () {
        Post::factory()->published()->count(5)->create()->each(
            fn (Post $post) => Picture::factory()->for($post)->create()
        );

        expect(Livewire::test(Home::class)->instance()->heroPosts())->toHaveCount(3);
    });
});

describe('trendingPosts()', function () {
    it('orders published posts by view count, descending', function () {
        $low = Post::factory()->published()->create();
        PostStats::factory()->for($low)->create(['view_count' => 3]);

        $high = Post::factory()->published()->create();
        PostStats::factory()->for($high)->create(['view_count' => 300]);

        $draft = Post::factory()->create();
        PostStats::factory()->for($draft)->create(['view_count' => 999]);

        $trending = Livewire::test(Home::class)->instance()->trendingPosts();

        expect($trending->pluck('id')->toArray())->toBe([$high->id, $low->id]);
    });
});

describe('recentSection()', function () {
    it('only shows published posts, most recent first, capped at 4', function () {
        $draft = Post::factory()->create();
        $published = Post::factory()->published()->count(6)->create();

        $recent = Livewire::test(Home::class)->instance()->recentSection();

        expect($recent)->toHaveCount(4);
        expect($recent->pluck('id'))->not->toContain($draft->id);
    });
});

describe('sectionBlocks()', function () {
    it('produces one block per section that has published posts, and skips empty sections', function () {
        Post::factory()->published()->inSection(PostSection::Politics)->create();
        // No posts at all for Sports, Culture, Science, Opinion, World.

        $blocks = Livewire::test(Home::class)->instance()->sectionBlocks();

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['slug'])->toBe('politics')
            ->and($blocks[0]['layout'])->toBe('four-col');
    });

    it('excludes drafts from a section\'s block', function () {
        Post::factory()->published()->inSection(PostSection::Politics)->create();
        Post::factory()->inSection(PostSection::Politics)->create(); // draft

        $blocks = Livewire::test(Home::class)->instance()->sectionBlocks();
        $politics = collect($blocks)->firstWhere('slug', 'politics');

        expect($politics['posts'])->toHaveCount(1);
    });

    it('only produces blocks for sections reachable from the nav menu', function () {
        // Science/Opinion/World are valid PostSection cases (existing posts
        // keep working) but no longer part of the visible nav — the home
        // page shouldn't advertise a section nobody can click through to.
        Post::factory()->published()->inSection(PostSection::World)->create();
        Post::factory()->published()->inSection(PostSection::Opinion)->create();
        Post::factory()->published()->inSection(PostSection::Science)->create();

        $blocks = collect(Livewire::test(Home::class)->instance()->sectionBlocks())->keyBy('slug');

        expect($blocks->has('world'))->toBeFalse()
            ->and($blocks->has('opinion'))->toBeFalse()
            ->and($blocks->has('science'))->toBeFalse();
    });

    it('assigns the expected layout per visible section', function () {
        foreach (PostSection::visible() as $section) {
            Post::factory()->published()->inSection($section)->create();
        }

        $blocks = collect(Livewire::test(Home::class)->instance()->sectionBlocks())->keyBy('slug');

        expect($blocks['politics']['layout'])->toBe('four-col')
            ->and($blocks['sports']['layout'])->toBe('hero-mini')
            ->and($blocks['culture']['layout'])->toBe('three-col')
            ->and($blocks['actualite']['layout'])->toBe('three-col')
            ->and($blocks['editorial']['layout'])->toBe('three-col');
    });
});

describe('loadMoreSection()', function () {
    it('expands a single section\'s post limit by 4 without affecting other sections', function () {
        Post::factory()->published()->inSection(PostSection::Politics)->count(10)->create();
        Post::factory()->published()->inSection(PostSection::Sports)->count(10)->create();

        $component = Livewire::test(Home::class);

        $blocks = collect($component->instance()->sectionBlocks())->keyBy('slug');
        expect($blocks['politics']['posts'])->toHaveCount(4)
            ->and($blocks['sports']['posts'])->toHaveCount(4);

        $component->call('loadMoreSection', 'politics');

        $blocks = collect($component->instance()->sectionBlocks())->keyBy('slug');
        expect($blocks['politics']['posts'])->toHaveCount(8)
            ->and($blocks['sports']['posts'])->toHaveCount(4);
    });

    it('ignores an unknown section slug', function () {
        $component = Livewire::test(Home::class);

        $component->call('loadMoreSection', 'not-a-real-section');

        expect($component->get('sectionLimits'))->not->toHaveKey('not-a-real-section');
    });
});
