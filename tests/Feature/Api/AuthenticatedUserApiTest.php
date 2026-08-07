<?php

/**
 * tests/Feature/Api/AuthenticatedUserApiTest.php
 *
 * Covers GET /api/user — the one Sanctum-protected endpoint, proving the
 * token guard actually works end-to-end (issue, authenticate, revoke).
 */

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('rejects requests without a token', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('rejects requests with a garbage bearer token', function () {
    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('returns the token owner for a valid Sanctum token', function () {
    $user = User::factory()->asUser()->create(['name' => 'Token Holder']);
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Token Holder');
});

it('rejects a revoked token', function () {
    $user = User::factory()->asUser()->create();
    $newToken = $user->createToken('test-token');
    $plainTextToken = $newToken->plainTextToken;
    $newToken->accessToken->delete();

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/user')
        ->assertUnauthorized();
});
