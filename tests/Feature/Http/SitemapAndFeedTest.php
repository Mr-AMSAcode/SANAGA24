<?php

/**
 * tests/Feature/Http/SitemapAndFeedTest.php
 *
 * Covers the /sitemap.xml and /feed.xml routes: correct content type,
 * only published posts are listed, and drafts never leak through.
 */

use App\Models\Post;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
});

describe('sitemap.xml', function () {
    it('lists published posts with a valid content type', function () {
        $published = Post::factory()->published()->create();
        $draft = Post::factory()->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('posts.show', $published), false)
            ->assertDontSee(route('posts.show', $draft), false);
    });

    it('includes the homepage and every section route', function () {
        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('home'), false)
            ->assertSee(route('politics'), false)
            ->assertSee(route('world'), false);
    });
});

describe('feed.xml', function () {
    it('lists published posts with a valid RSS content type', function () {
        $published = Post::factory()->published()->create(['title' => 'A Feed-Worthy Headline']);
        $draft = Post::factory()->create(['title' => 'Should Never Appear In The Feed']);

        $response = $this->get('/feed.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('A Feed-Worthy Headline')
            ->assertDontSee('Should Never Appear In The Feed');
    });

    it('caps at the 50 most recent published posts', function () {
        Post::factory()->published()->count(55)->create();

        $response = $this->get('/feed.xml');

        expect(substr_count($response->getContent(), '<item>'))->toBe(50);
    });
});
