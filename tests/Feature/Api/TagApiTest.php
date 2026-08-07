<?php

/**
 * tests/Feature/Api/TagApiTest.php
 *
 * Covers GET /api/tags — public, read-only.
 */

use App\Models\Post;
use App\Models\Tag;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lists only tags attached to at least one published post', function () {
    $visible = Tag::factory()->create(['name' => 'Elections', 'slug' => 'elections']);
    $hidden = Tag::factory()->create(['name' => 'Draft Only', 'slug' => 'draft-only']);

    $published = Post::factory()->published()->create();
    $published->tags()->attach($visible);

    $draft = Post::factory()->create();
    $draft->tags()->attach($hidden);

    $response = $this->getJson('/api/tags');

    $slugs = collect($response->json('data'))->pluck('slug');

    expect($slugs)->toContain('elections')
        ->and($slugs)->not->toContain('draft-only');
});

it('orders tags alphabetically by name', function () {
    $post = Post::factory()->published()->create();
    $post->tags()->attach(Tag::factory()->create(['name' => 'Zebra', 'slug' => 'zebra']));
    $post->tags()->attach(Tag::factory()->create(['name' => 'Apple', 'slug' => 'apple']));

    $response = $this->getJson('/api/tags');

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Apple', 'Zebra']);
});

it('shapes each tag with just name and slug', function () {
    $post = Post::factory()->published()->create();
    $post->tags()->attach(Tag::factory()->create(['name' => 'Elections', 'slug' => 'elections']));

    $this->getJson('/api/tags')->assertJsonStructure(['data' => [['name', 'slug']]]);
});
