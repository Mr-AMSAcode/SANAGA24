<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;

new class extends Component {
    #[Rule('required|string|max:255')]
    public string $tokenName = '';

    /**
     * The plaintext token, shown exactly once right after creation —
     * Sanctum only stores the hash, so this is the only chance to see it.
     */
    public ?string $plainTextToken = null;

    public function createToken(): void
    {
        $this->validate();

        $token = Auth::user()->createToken($this->tokenName);

        $this->plainTextToken = $token->plainTextToken;
        $this->tokenName = '';

        unset($this->tokens);
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->where('id', $tokenId)->delete();

        unset($this->tokens);
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    #[Computed]
    public function tokens()
    {
        return Auth::user()->tokens()->latest()->get();
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('API Tokens') }}</flux:heading>

    <x-pages::settings.layout :heading="__('API Tokens')" :subheading="__('Manage personal access tokens for the public JSON API')">
        <div class="my-6 w-full space-y-6">
            @if ($plainTextToken)
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
                    <flux:text class="font-medium">
                        {{ __('Copy this token now — you will not be able to see it again.') }}
                    </flux:text>
                    <flux:input readonly :value="$plainTextToken" copyable class="mt-2 font-mono text-sm" />
                    <flux:button size="sm" variant="ghost" class="mt-2" wire:click="dismissToken">
                        {{ __('Done') }}
                    </flux:button>
                </div>
            @endif

            <form wire:submit="createToken" class="flex items-end gap-4">
                <div class="flex-1">
                    <flux:input wire:model="tokenName" :label="__('Token name')" type="text" placeholder="{{ __('e.g. My Mobile App') }}" />
                </div>
                <flux:button variant="primary" type="submit" data-test="create-token-button">
                    {{ __('Create Token') }}
                </flux:button>
            </form>

            <div>
                <flux:heading size="sm">{{ __('Existing tokens') }}</flux:heading>

                @forelse ($this->tokens as $token)
                    <div class="flex items-center justify-between border-b border-zinc-100 py-3 dark:border-zinc-700" wire:key="token-{{ $token->id }}">
                        <div>
                            <flux:text class="font-medium">{{ $token->name }}</flux:text>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('Last used') }}: {{ $token->last_used_at?->diffForHumans() ?? __('Never') }}
                            </flux:text>
                        </div>
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="{{ __('Revoke this token?') }}"
                        >
                            {{ __('Revoke') }}
                        </flux:button>
                    </div>
                @empty
                    <flux:text class="text-zinc-500">{{ __('No API tokens yet.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </x-pages::settings.layout>
</section>
