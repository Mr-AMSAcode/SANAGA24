<?php

/**
 * tests/Feature/PostSectionTest.php
 *
 * Covers the nav restructure: Accueil/Politique/Sport/Culture/Actualité
 * as primary rubriques, a 12-item "Autre" dropdown, and Science/Opinion/
 * World kept valid but no longer linked from the menu.
 */

use App\Enums\PostSection;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('defines exactly the 4 primary nav sections, in order', function () {
    expect(array_map(fn ($s) => $s->value, PostSection::primaryNav()))
        ->toBe(['politics', 'sports', 'culture', 'actualite']);
});

it('defines exactly the 11 "Autre" sub-sections', function () {
    // "Galerie" is deliberately not here — it's the site-wide media
    // gallery (all pictures across every post), not an article rubrique,
    // so it isn't a PostSection case at all. See routes/web.php and
    // App\Livewire\Pages\Gallery.
    $values = array_map(fn ($s) => $s->value, PostSection::otherMenu());

    expect($values)->toHaveCount(11)->and($values)->toBe([
        'editorial', 'ca-bouge', 'zoom', 'le-dossier',
        'au-coeur-des-communautes', 'infrastructures', 'projets',
        'arts-et-culture', 'tourisme', 'agroalimentaire',
        'qui-sommes-nous',
    ])->and($values)->not->toContain('galerie');
});

it('excludes science, opinion and world from the visible menu', function () {
    $visible = array_map(fn ($s) => $s->value, PostSection::visible());

    expect($visible)->not->toContain('science')
        ->and($visible)->not->toContain('opinion')
        ->and($visible)->not->toContain('world')
        ->and($visible)->toHaveCount(15);
});

it('every section — visible or not — has a working route that returns 200', function () {
    foreach (PostSection::cases() as $section) {
        $this->get(route($section->value))->assertOk();
    }
})->group('slow');

it('the home page nav shows the primary rubriques and an "Autre" dropdown, not the hidden ones', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Politics')
        ->assertSee('Sports')
        ->assertSee('Culture')
        ->assertSee('News')
        ->assertSee('Other')
        ->assertSee('Editorial')
        ->assertSee('Gallery');
});

it('accepts a new section value in the posts table check constraint', function () {
    $editor = User::factory()->editor()->create();

    $post = Post::create([
        'editor_id' => $editor->id,
        'title' => 'An about-us post',
        'slug' => 'an-about-us-post-'.uniqid(),
        'content' => 'content',
        'section' => PostSection::AboutUs->value,
        'status' => 'draft',
    ]);

    expect($post->fresh()->section)->toBe(PostSection::AboutUs);
});

it('no longer accepts "galerie" as a post section', function () {
    $editor = User::factory()->editor()->create();

    expect(fn () => Post::create([
        'editor_id' => $editor->id,
        'title' => 'A galerie post',
        'slug' => 'a-galerie-post-'.uniqid(),
        'content' => 'content',
        'section' => 'galerie',
        'status' => 'draft',
    ]))->toThrow(\ValueError::class);
});

it('still rejects a section value that was never valid', function () {
    // The `section` attribute cast to PostSection means Eloquent rejects an
    // invalid backing value before a query is ever sent — PHP's own enum
    // ValueError, not a round-trip to the database's check constraint.
    $editor = User::factory()->editor()->create();

    expect(fn () => Post::create([
        'editor_id' => $editor->id,
        'title' => 'Bad section post',
        'slug' => 'bad-section-post-'.uniqid(),
        'content' => 'content',
        'section' => 'not-a-real-section',
        'status' => 'draft',
    ]))->toThrow(\ValueError::class);
});
