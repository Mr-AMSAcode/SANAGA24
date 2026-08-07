<?php

/**
 * tests/Feature/Http/LocaleTest.php
 *
 * Covers the FR/EN language switcher: the /lang/{locale} route, the
 * SetLocale middleware that reads it back from the session on every
 * subsequent request, and that the app actually renders translated
 * content once switched (not just that app()->getLocale() changed).
 */

use App\Http\Middleware\SetLocale;

it('defaults to the configured app locale with no session value', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    expect(app()->getLocale())->toBe(config('app.locale'));
});

it('switches to French and persists it across the next request', function () {
    $this->get('/lang/fr')->assertRedirect();

    $response = $this->get(route('home'));

    $response->assertOk()->assertSee('Accueil');
    expect(app()->getLocale())->toBe('fr');
});

it('switches back to English', function () {
    $this->withSession(['locale' => 'fr']);

    $this->get('/lang/en')->assertRedirect();

    $response = $this->get(route('home'));

    $response->assertOk()->assertSee('Home');
    expect(app()->getLocale())->toBe('en');
});

it('redirects back to the referring page after switching', function () {
    $response = $this->from(route('politics'))->get('/lang/fr');

    $response->assertRedirect(route('politics'));
});

it('rejects a locale outside the supported list', function () {
    $this->get('/lang/de')->assertNotFound();
});

it('falls back to the app default when the session holds an unsupported locale', function () {
    // Simulates a stale/tampered session value rather than a real switch.
    $this->withSession(['locale' => 'de']);

    $this->get(route('home'))->assertOk();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

it('lists exactly the locales the site ships translations for', function () {
    expect(SetLocale::SUPPORTED)->toBe(['en', 'fr']);
});

it('translates nav, footer and section labels together on one page', function () {
    $this->get('/lang/fr');

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Accueil')
        ->assertSee('Politique')
        ->assertSee('Autre')
        ->assertDontSee('Sign in');
});
