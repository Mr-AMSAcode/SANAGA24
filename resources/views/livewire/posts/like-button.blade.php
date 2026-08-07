{{--
    Polymorphic like button.
    Can be dropped into any Blade template for Post or Comment models.
    Usage: <livewire:posts.like-button :target="$post" />
--}}
<button
    wire:click="toggle"
    wire:loading.attr="disabled"
    @class([
        'inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full transition-all',
        'bg-amber-400 text-stone-900 ring-2 ring-amber-300 ring-offset-1' => $liked,
        'bg-stone-100 text-stone-500 hover:bg-amber-50 hover:text-amber-600' => ! $liked,
    ])
    title="{{ $liked ? __('Unlike') : __('Like') }}"
>
    {{-- Heart icon --}}
    <svg
        class="w-3.5 h-3.5 transition-transform {{ $liked ? 'scale-110' : 'scale-100' }}"
        fill="{{ $liked ? 'currentColor' : 'none' }}"
        stroke="currentColor"
        stroke-width="{{ $liked ? '0' : '2' }}"
        viewBox="0 0 24 24"
    >
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682
                 a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
    </svg>

    <span>{{ $count }}</span>
</button>
