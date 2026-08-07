<div>
    @if ($subscribed)
        <p class="text-sm text-amber-400 font-semibold">
            {{ __("You're subscribed — thanks for joining!") }}
        </p>
    @else
        <form wire:submit="subscribe" class="flex items-center gap-2">
            <input wire:model="email" type="email" placeholder="you@example.com"
                   class="flex-1 min-w-0 px-3 py-2 text-sm text-white placeholder-white/40 bg-white/10
                          border border-white/10 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400"/>
            <button type="submit" wire:loading.attr="disabled"
                    class="flex-shrink-0 px-4 py-2 text-xs font-bold text-stone-900 bg-amber-400
                           hover:bg-amber-500 rounded-lg transition-colors">
                <span wire:loading.remove wire:target="subscribe">{{ __('Subscribe') }}</span>
                <span wire:loading wire:target="subscribe">…</span>
            </button>
        </form>
        @error('email')
        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
        @enderror
    @endif
</div>
