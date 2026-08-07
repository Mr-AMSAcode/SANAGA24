<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $this->assertNotBot($input);

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Every self-registered account is a reader by default — without
        // this, the account has zero permissions and can't comment, like,
        // or reply to anything (all gated behind role-based permissions).
        $user->assignRole('user');

        return $user;
    }

    /**
     * Two lightweight, invisible-to-humans bot signals:
     *  - the honeypot field ("website") got filled in, which only an
     *    auto-filling bot would do since it's hidden off-screen;
     *  - the form was submitted implausibly fast for a human to have
     *    actually read it and typed an answer.
     *
     * Both fail the same generic way — no hint to an attacker about
     * which check tripped or that anti-bot logic exists at all.
     */
    private function assertNotBot(array $input): void
    {
        $genericFailure = fn () => throw ValidationException::withMessages([
            'email' => __('Something went wrong. Please try again.'),
        ]);

        if (filled($input['website'] ?? null)) {
            $genericFailure();
        }

        try {
            // The blade uses the encrypt() helper (serialize=true), so this
            // must decrypt with unserialize=true too — decryptString()/
            // encryptString() are the non-serializing counterparts and
            // would silently mis-decode an int into "0".
            $renderedAt = (int) Crypt::decrypt($input['form_rendered_at'] ?? '');
        } catch (\Exception) {
            $genericFailure();

            return;
        }

        if (now()->timestamp - $renderedAt < 2) {
            $genericFailure();
        }
    }
}
