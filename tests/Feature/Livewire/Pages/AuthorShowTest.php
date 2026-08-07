<?php

/**
 * tests/Feature/Livewire/Pages/AuthorShowTest.php
 *
 * Covers App\Livewire\Pages\AuthorShow: the public byline page listing
 * an editor's published work. 404s for anyone with no published posts,
 * and never leaks drafts or another editor's articles.
 */

use App\Livewire\Pages\AuthorShow;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('shows the page for an editor with at least one published post', function () {
    $editor = User::factory()->editor()->create();
    Post::factory()->published()->for($editor, 'editor')->create();

    Livewire::test(AuthorShow::class, ['author' => $editor])->assertOk();
});

it('404s for a user with no published posts', function () {
    $editor = User::factory()->editor()->create();
    Post::factory()->for($editor, 'editor')->create(); // draft only

    Livewire::test(AuthorShow::class, ['author' => $editor])->assertNotFound();
});

it('404s for a regular reader who has never authored anything', function () {
    $user = User::factory()->asUser()->create();

    Livewire::test(AuthorShow::class, ['author' => $user])->assertNotFound();
});

it('only lists this author\'s published posts, not drafts or other editors\' work', function () {
    $editor = User::factory()->editor()->create();
    $otherEditor = User::factory()->editor()->create();

    $published = Post::factory()->published()->for($editor, 'editor')->create();
    Post::factory()->for($editor, 'editor')->create(); // own draft, excluded
    Post::factory()->published()->for($otherEditor, 'editor')->create(); // someone else's

    $posts = Livewire::test(AuthorShow::class, ['author' => $editor])->instance()->posts();

    expect($posts->pluck('id')->toArray())->toBe([$published->id]);
});

it('counts only published posts', function () {
    $editor = User::factory()->editor()->create();
    Post::factory()->published()->for($editor, 'editor')->count(3)->create();
    Post::factory()->for($editor, 'editor')->create(); // draft, not counted

    $count = Livewire::test(AuthorShow::class, ['author' => $editor])->instance()->publishedCount();

    expect($count)->toBe(3);
});
