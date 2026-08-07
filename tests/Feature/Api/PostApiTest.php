<?php

/**
 * tests/Feature/Api/PostApiTest.php
 *
 * Covers the public, read-only GET /api/posts and GET /api/posts/{slug}
 * endpoints. The central invariant across every test here: unpublished
 * posts (draft/scheduled/archived) must never be reachable through the
 * API, whether by listing, filtering, or guessing a slug directly.
 */

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Picture;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('GET /api/posts', function () {
    it('lists only published posts', function () {
        $published = Post::factory()->published()->withStats()->create();
        Post::factory()->create(); // draft
        Post::factory()->archived()->create();
        Post::factory()->state(['status' => PostStatus::Scheduled])->create();

        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        expect($ids)->toHaveCount(1)
            ->and($ids->first())->toBe($published->id);
    });

    it('shapes each post with summary fields, author, tags and featured image', function () {
        $post = Post::factory()->published()->withStats()->create();
        Picture::factory()->for($post)->featured()->create();
        $tag = Tag::factory()->create(['name' => 'Elections', 'slug' => 'elections']);
        $post->tags()->attach($tag);

        $response = $this->getJson('/api/posts');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.title', $post->title)
            ->assertJsonPath('data.0.slug', $post->slug)
            ->assertJsonPath('data.0.author.id', $post->editor_id)
            ->assertJsonPath('data.0.tags.0.slug', 'elections')
            ->assertJsonPath('data.0.featured_image_url', $post->pictures()->featured()->first()->url)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'slug', 'section', 'excerpt',
                    'featured_image_url', 'published_at',
                    'author' => ['id', 'name', 'initials'],
                    'tags',
                    'stats' => ['views', 'likes', 'comments'],
                ]],
                'links',
                'meta',
            ]);
    });

    it('never leaks full content — only a truncated excerpt', function () {
        $longContent = str_repeat('word ', 100);
        Post::factory()->published()->withStats()->create(['content' => $longContent]);

        $response = $this->getJson('/api/posts');

        $excerpt = $response->json('data.0.excerpt');

        expect(strlen($excerpt))->toBeLessThan(strlen($longContent))
            ->and($response->json('data.0'))->not->toHaveKey('content');
    });

    it('filters by section', function () {
        $politics = Post::factory()->published()->withStats()->inSection(PostSection::Politics)->create();
        Post::factory()->published()->withStats()->inSection(PostSection::Sports)->create();

        $response = $this->getJson('/api/posts?section=politics');

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toHaveCount(1)->and($ids->first())->toBe($politics->id);
    });

    it('filters by tag slug', function () {
        $tagged = Post::factory()->published()->withStats()->create();
        Post::factory()->published()->withStats()->create();
        $tag = Tag::factory()->create(['slug' => 'elections']);
        $tagged->tags()->attach($tag);

        $response = $this->getJson('/api/posts?tag=elections');

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toHaveCount(1)->and($ids->first())->toBe($tagged->id);
    });

    it('filters by author id', function () {
        $editor = User::factory()->editor()->create();
        $theirs = Post::factory()->published()->withStats()->for($editor, 'editor')->create();
        Post::factory()->published()->withStats()->create();

        $response = $this->getJson('/api/posts?author='.$editor->id);

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toHaveCount(1)->and($ids->first())->toBe($theirs->id);
    });

    it('full-text searches by keyword', function () {
        $match = Post::factory()->published()->withStats()->create(['title' => 'Historic Election Results Announced']);
        Post::factory()->published()->withStats()->create(['title' => 'Local Football Match Recap']);

        $response = $this->getJson('/api/posts?q=election');

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($match->id);
    });

    it('paginates results at 15 per page', function () {
        Post::factory()->published()->withStats()->count(20)->create();

        $response = $this->getJson('/api/posts');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20);
    });
});

describe('GET /api/posts/{slug}', function () {
    it('shows a published post with full content and the whole picture gallery', function () {
        $post = Post::factory()->published()->withStats()->create();
        Picture::factory()->for($post)->featured()->create();
        Picture::factory()->for($post)->create();

        $response = $this->getJson("/api/posts/{$post->slug}");

        $response->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.content', $post->content)
            ->assertJsonCount(2, 'data.pictures');
    });

    it('returns 404 for a draft post', function () {
        $post = Post::factory()->create();

        $this->getJson("/api/posts/{$post->slug}")->assertNotFound();
    });

    it('returns 404 for a scheduled post', function () {
        $post = Post::factory()->state(['status' => PostStatus::Scheduled])->create();

        $this->getJson("/api/posts/{$post->slug}")->assertNotFound();
    });

    it('returns 404 for an archived post', function () {
        $post = Post::factory()->archived()->create();

        $this->getJson("/api/posts/{$post->slug}")->assertNotFound();
    });

    it('returns 404 for a nonexistent slug', function () {
        $this->getJson('/api/posts/does-not-exist')->assertNotFound();
    });
});
