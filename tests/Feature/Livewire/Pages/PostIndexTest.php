<?php

/**
 * tests/Feature/Livewire/Pages/PostIndexTest.php
 *
 * Covers App\Livewire\Pages\PostIndex: the public "browse" page backing
 * both /browse and the per-section routes (/politics, /section/{x}, …).
 * Only published posts must ever appear here, regardless of filter/sort.
 */

use App\Enums\PostSection;
use App\Livewire\Pages\PostIndex;
use App\Models\Post;
use App\Models\PostStats;
use App\Models\Tag;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('renders for guests', function () {
    Livewire::test(PostIndex::class)->assertOk();
});

describe('section route parameter', function () {
    it('scopes to the given section when mounted with one', function () {
        Livewire::test(PostIndex::class, ['section' => 'sports'])
            ->assertSet('section', 'sports');
    });

    it('rejects an unknown section with a 404', function () {
        Livewire::test(PostIndex::class, ['section' => 'not-a-section'])
            ->assertNotFound();
    });

    it('currentSectionLabel reflects the active section, or "All Sections"', function () {
        expect(Livewire::test(PostIndex::class)->instance()->currentSectionLabel())->toBe('All Sections');
        expect(Livewire::test(PostIndex::class, ['section' => 'culture'])->instance()->currentSectionLabel())->toBe('Culture');
    });
});

describe('tag route parameter', function () {
    it('scopes to posts carrying the given tag', function () {
        $tag = Tag::factory()->create(['name' => 'Elections', 'slug' => 'elections']);
        $tagged = Post::factory()->published()->create();
        $tagged->tags()->attach($tag);
        Post::factory()->published()->create(); // untagged

        $posts = Livewire::test(PostIndex::class, ['tag' => 'elections'])->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$tagged->id]);
    });

    it('rejects an unknown tag slug with a 404', function () {
        Livewire::test(PostIndex::class, ['tag' => 'no-such-tag'])->assertNotFound();
    });

    it('currentSectionLabel shows the tag name when browsing by tag', function () {
        Tag::factory()->create(['name' => 'Elections', 'slug' => 'elections']);

        expect(Livewire::test(PostIndex::class, ['tag' => 'elections'])->instance()->currentSectionLabel())
            ->toBe('#Elections');
    });
});

describe('posts()', function () {
    it('only ever lists published posts', function () {
        $published = Post::factory()->published()->create();
        Post::factory()->create(); // draft
        Post::factory()->archived()->create();

        $posts = Livewire::test(PostIndex::class)->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$published->id]);
    });

    it('filters by section', function () {
        $sports = Post::factory()->published()->inSection(PostSection::Sports)->create();
        Post::factory()->published()->inSection(PostSection::World)->create();

        $posts = Livewire::test(PostIndex::class, ['section' => 'sports'])->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$sports->id]);
    });

    it('searches by title', function () {
        $match = Post::factory()->published()->create(['title' => 'The Great Migration of Whales']);
        Post::factory()->published()->create(['title' => 'Something Unrelated']);

        $posts = Livewire::test(PostIndex::class)
            ->set('search', 'migration')
            ->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$match->id]);
    });

    it('full-text searches the body content too, not just the title', function () {
        $match = Post::factory()->published()->create([
            'title' => 'Local News Roundup',
            'content' => str_repeat('filler ', 20).'A rare albino elephant was spotted near the reserve.',
        ]);
        Post::factory()->published()->create(['title' => 'Unrelated', 'content' => str_repeat('filler ', 20)]);

        $posts = Livewire::test(PostIndex::class)
            ->set('search', 'albino elephant')
            ->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$match->id]);
    });

    it('ranks a title match above a content-only match for the same term', function () {
        $contentOnly = Post::factory()->published()->create([
            'title' => 'Weekly Roundup',
            'content' => str_repeat('filler ', 20).'astronomy was briefly mentioned here.',
        ]);
        $titleMatch = Post::factory()->published()->create([
            'title' => 'Astronomy Breakthrough Announced',
            'content' => str_repeat('filler ', 20),
        ]);

        $posts = Livewire::test(PostIndex::class)
            ->set('search', 'astronomy')
            ->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$titleMatch->id, $contentOnly->id]);
    });

    it('does not match unrelated words', function () {
        Post::factory()->published()->create(['title' => 'Completely Different Subject Matter']);

        $posts = Livewire::test(PostIndex::class)
            ->set('search', 'giraffe')
            ->instance()->posts();

        expect($posts)->toHaveCount(0);
    });

    it('sorts by popularity (view count)', function () {
        $low = Post::factory()->published()->create();
        PostStats::factory()->for($low)->create(['view_count' => 1]);
        $high = Post::factory()->published()->create();
        PostStats::factory()->for($high)->create(['view_count' => 100]);

        $posts = Livewire::test(PostIndex::class)->set('sort', 'popular')->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$high->id, $low->id]);
    });

    it('sorts by most commented', function () {
        $low = Post::factory()->published()->create();
        PostStats::factory()->for($low)->create(['comment_count' => 1]);
        $high = Post::factory()->published()->create();
        PostStats::factory()->for($high)->create(['comment_count' => 20]);

        $posts = Livewire::test(PostIndex::class)->set('sort', 'commented')->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$high->id, $low->id]);
    });

    it('defaults to latest first', function () {
        $older = Post::factory()->published()->create(['created_at' => now()->subDays(2)]);
        $newer = Post::factory()->published()->create(['created_at' => now()]);

        $posts = Livewire::test(PostIndex::class)->instance()->posts();

        expect($posts->pluck('id')->toArray())->toBe([$newer->id, $older->id]);
    });
});
