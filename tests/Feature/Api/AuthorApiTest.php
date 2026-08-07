<?php

/**
 * tests/Feature/Api/AuthorApiTest.php
 *
 * Covers GET /api/authors/{author} — public, read-only. Mirrors the
 * public AuthorShow page's rule: only editors with at least one
 * published post get a reachable profile.
 */

use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('shows an author profile with their published post count', function () {
    $editor = User::factory()->editor()->create(['name' => 'Jane Reporter']);
    Post::factory()->published()->for($editor, 'editor')->count(3)->create();
    Post::factory()->for($editor, 'editor')->create(); // draft, shouldn't count

    $response = $this->getJson("/api/authors/{$editor->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $editor->id)
        ->assertJsonPath('data.name', 'Jane Reporter')
        ->assertJsonPath('published_post_count', 3)
        ->assertJsonMissingPath('data.email');
});

it('returns 404 for a user with no published posts', function () {
    $editor = User::factory()->editor()->create();
    Post::factory()->for($editor, 'editor')->create(); // draft only

    $this->getJson("/api/authors/{$editor->id}")->assertNotFound();
});

it('returns 404 for a nonexistent author id', function () {
    $this->getJson('/api/authors/999999')->assertNotFound();
});
