<?php

/**
 * tests/Feature/Http/LayoutTest.php
 *
 * Guards against two real bugs found by manually browsing the site
 * rather than by the test suite (neither is visible to Livewire::test()
 * or a plain HTTP assertion on the component's own view):
 *
 *  1. partials.nav / partials.footer were written and repeatedly edited
 *     but never @include'd anywhere in the real layout — completely
 *     dead code. Fixed by wiring them into layouts/app/header.blade.php.
 *  2. The logo was wrapped in `brightness-0 invert`, which flattens
 *     every colour in the multi-tone SVG (blue wordmark, red circle,
 *     white "24") to a solid white silhouette — so the red circle and
 *     the numbers on it become invisible against the white-on-white.
 */

it('actually renders the nav and footer partials, not just the component view', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Sign in')
        ->assertSee('Register')
        ->assertSee('Sitemap')
        ->assertSee('RSS');
});

it('shows admin/editor links in the nav for a logged-in admin', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $admin = \App\Models\User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('home'));

    $response->assertOk()
        ->assertSee('Admin')
        ->assertSee('Editor')
        ->assertDontSee('Sign in');
});

it('never re-applies a filter that flattens the logo to a single colour', function () {
    $response = $this->get(route('home'));

    // brightness-0 (or grayscale/invert paired with it) turns every shape
    // in the logo the same colour, destroying the contrast between the
    // red circle and the white "24" sitting on it.
    $response->assertOk()->assertDontSee('brightness-0');
});

it('renders the current logo asset, not a stale or superseded one', function () {
    // logo.jpeg (the file as supplied) has a solid white background.
    // A transparent cutout (logo.png) was tried, but the user explicitly
    // asked to keep the white background — it reads more clearly against
    // the dark navy nav/footer than the transparent version did.
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('logo.jpeg')
        ->assertDontSee('Logo_Sanaga24.png');
});
