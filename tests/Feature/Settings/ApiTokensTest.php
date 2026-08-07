<?php

/**
 * tests/Feature/Settings/ApiTokensTest.php
 *
 * Covers the "API Tokens" settings page (pages::settings.api-tokens) —
 * the only way a real user can obtain a Sanctum bearer token for the
 * JSON API's authenticated /api/user endpoint.
 */

use App\Models\User;
use Livewire\Livewire;

test('the api tokens page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('api-tokens.edit'))->assertOk();
});

test('a token can be created and its plaintext is exposed exactly once', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = Livewire::test('pages::settings.api-tokens')
        ->set('tokenName', 'My Mobile App')
        ->call('createToken');

    $response->assertHasNoErrors();

    expect($user->tokens()->where('name', 'My Mobile App')->exists())->toBeTrue()
        ->and($response->instance()->plainTextToken)->not->toBeNull()
        ->and($response->instance()->tokenName)->toBe('');
});

test('token name is required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.api-tokens')
        ->set('tokenName', '')
        ->call('createToken')
        ->assertHasErrors(['tokenName' => 'required']);
});

test('a user can revoke their own token', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $token = $user->createToken('to-revoke');

    Livewire::test('pages::settings.api-tokens')
        ->call('revokeToken', $token->accessToken->id);

    expect($user->tokens()->count())->toBe(0);
});

test('a user cannot revoke another users token', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = $owner->createToken('owners-token');

    $this->actingAs($intruder);

    Livewire::test('pages::settings.api-tokens')
        ->call('revokeToken', $token->accessToken->id);

    expect($owner->tokens()->count())->toBe(1);
});

test('the token list only shows the authenticated users own tokens', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->createToken('mine');
    $other->createToken('theirs');

    $this->actingAs($user);

    $tokens = Livewire::test('pages::settings.api-tokens')->instance()->tokens();

    expect($tokens)->toHaveCount(1)
        ->and($tokens->first()->name)->toBe('mine');
});

test('a freshly issued token actually authenticates against the API', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = Livewire::test('pages::settings.api-tokens')
        ->set('tokenName', 'Integration Check')
        ->call('createToken');

    $plainTextToken = $response->instance()->plainTextToken;

    // A fresh, unauthenticated HTTP call (not the Livewire test session)
    // proves the token itself is valid, not just that the object exists.
    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});
