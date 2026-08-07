<?php

/**
 * tests/Feature/Livewire/Pages/GalleryTest.php
 *
 * Covers App\Livewire\Pages\Gallery — the site-wide media gallery. Not a
 * Post category: it aggregates the Picture rows that already exist across
 * every published post, newest first.
 */

use App\Livewire\Pages\Gallery;
use App\Models\Picture;
use App\Models\Post;
use App\Models\Video;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('renders for guests', function () {
    $this->get(route('galerie'))->assertOk();
});

it('lists pictures from published posts only, newest first', function () {
    $old = Picture::factory()->for(Post::factory()->published())->create(['created_at' => now()->subDay()]);
    $new = Picture::factory()->for(Post::factory()->published())->create(['created_at' => now()]);
    Picture::factory()->for(Post::factory())->create(); // draft (factory default) — excluded

    $pictures = Livewire::test(Gallery::class)->instance()->pictures();

    expect($pictures)->toHaveCount(2)
        ->and($pictures->first()->id)->toBe($new->id)
        ->and($pictures->last()->id)->toBe($old->id);
});

it('paginates at 24 pictures per page', function () {
    Picture::factory()->for(Post::factory()->published())->count(30)->create();

    $pictures = Livewire::test(Gallery::class)->instance()->pictures();

    expect($pictures)->toHaveCount(24)
        ->and($pictures->total())->toBe(30);
});

it('shows an empty state with no published pictures', function () {
    $response = $this->get(route('galerie'));

    $response->assertOk()->assertSee('No photos yet.');
});

it('links each picture back to its post', function () {
    $post = Post::factory()->published()->create();
    Picture::factory()->for($post)->create();

    $response = $this->get(route('galerie'));

    $response->assertOk()->assertSee(route('posts.show', $post), false);
});

it('lists videos from published posts only, newest first', function () {
    $old = Video::factory()->embed()->for(Post::factory()->published())->create(['created_at' => now()->subDay()]);
    $new = Video::factory()->embed()->for(Post::factory()->published())->create(['created_at' => now()]);
    Video::factory()->embed()->for(Post::factory())->create(); // draft — excluded

    $videos = Livewire::test(Gallery::class)->instance()->videos();

    expect($videos)->toHaveCount(2)
        ->and($videos->first()->id)->toBe($new->id)
        ->and($videos->last()->id)->toBe($old->id);
});

it('paginates at 24 videos per page', function () {
    Video::factory()->embed()->for(Post::factory()->published())->count(30)->create();

    $videos = Livewire::test(Gallery::class)->instance()->videos();

    expect($videos)->toHaveCount(24)
        ->and($videos->total())->toBe(30);
});

it('switches tabs via setTab()', function () {
    Livewire::test(Gallery::class)
        ->assertSet('tab', 'photos')
        ->call('setTab', 'videos')
        ->assertSet('tab', 'videos');
});

it('shows an empty state with no published videos', function () {
    Livewire::test(Gallery::class)
        ->call('setTab', 'videos')
        ->assertSee('No videos yet.');
});

it('links each video back to its post', function () {
    $post = Post::factory()->published()->create();
    Video::factory()->embed()->for($post)->create();

    Livewire::test(Gallery::class)
        ->call('setTab', 'videos')
        ->assertSee(route('posts.show', $post), false);
});
