<?php

use App\Livewire\Posts\CommentThread;
use App\Models\Post;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/**
 * Valid payload as a genuine browser submission would send it: honeypot
 * left blank, and a render timestamp from a few seconds ago (comfortably
 * past the 2-second minimum the anti-bot check enforces).
 */
function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website' => '',
        'form_rendered_at' => encrypt(now()->subSeconds(5)->timestamp),
    ], $overrides);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), validRegistrationPayload());

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
});

test('newly registered users get the "user" role and can actually comment', function () {
    // Regression test: registration used to leave the account with zero
    // roles/permissions, so every comment/like/reply attempt 403'd — this
    // only ever showed up by actually registering through the real form
    // and trying to comment, never in tests that used a factory state
    // (User::factory()->asUser()) which assigns the role directly.
    $this->post(route('register.store'), validRegistrationPayload());

    $user = auth()->user();
    expect($user->hasRole('user'))->toBeTrue();

    $post = Post::factory()->published()->create();

    Livewire::actingAs($user)
        ->test(CommentThread::class, ['post' => $post])
        ->set('newComment', 'Glad I found this article.')
        ->call('postComment')
        ->assertHasNoErrors()
        ->assertStatus(200);

    expect($post->comments()->where('user_id', $user->id)->exists())->toBeTrue();
});

describe('anti-bot protection', function () {
    it('rejects registration when the honeypot field is filled in', function () {
        $response = $this->post(route('register.store'), validRegistrationPayload([
            'website' => 'https://spam.example.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('rejects registration submitted faster than a human plausibly could', function () {
        $response = $this->post(route('register.store'), validRegistrationPayload([
            'form_rendered_at' => encrypt(now()->timestamp),
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('rejects registration when the render timestamp is missing', function () {
        $response = $this->post(route('register.store'), validRegistrationPayload([
            'form_rendered_at' => '',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('rejects registration when the render timestamp is tampered with', function () {
        $response = $this->post(route('register.store'), validRegistrationPayload([
            'form_rendered_at' => 'not-a-valid-encrypted-value',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });
});