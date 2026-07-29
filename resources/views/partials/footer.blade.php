@php use App\Enums\PostSection; @endphp

<footer class="bg-[#0d1b4b] text-white mt-16">

    {{-- ── Main footer body ──────────────────────────────────────────── --}}
    <div class="container mx-auto max-w-7xl px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-10">

            {{-- Brand column --}}
            <div class="max-w-xs">
                <a wire:navigate href="{{ route('home') }}" class="inline-block mb-4">
                    <x-app-logo-icon class="h-10 w-auto brightness-0 invert" />
                </a>
                <p class="text-sm text-white/50 leading-relaxed">
                    Your trusted source for breaking news, in-depth analysis and stories
                    that matter — across politics, sports, culture, science, opinion and the world.
                </p>
                <div class="flex items-center gap-3 mt-5">
                    {{-- Twitter / X --}}
                    <a href="#" aria-label="Follow on X"
                       class="w-8 h-8 rounded-full border border-white/20 flex items-center justify-center
                              text-white/50 hover:text-white hover:border-white/50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.259 5.632zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                    </a>
                    {{-- Facebook --}}
                    <a href="#" aria-label="Follow on Facebook"
                       class="w-8 h-8 rounded-full border border-white/20 flex items-center justify-center
                              text-white/50 hover:text-white hover:border-white/50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    {{-- YouTube --}}
                    <a href="#" aria-label="Watch on YouTube"
                       class="w-8 h-8 rounded-full border border-white/20 flex items-center justify-center
                              text-white/50 hover:text-white hover:border-white/50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Sections nav — PostSection enum --}}
            <div>
                <h3 class="text-[11px] font-black uppercase tracking-widest text-white/40 mb-4">
                    Sections
                </h3>
                <ul class="grid grid-cols-2 sm:grid-cols-3 gap-x-8 gap-y-2">
                    <li>
                        <a wire:navigate href="{{ route('home') }}"
                           class="text-sm text-white/60 hover:text-amber-400 transition-colors">
                            Home
                        </a>
                    </li>
                    @foreach (PostSection::cases() as $section)
                        <li>
                            <a wire:navigate href="{{ route($section->value) }}"
                               class="text-sm text-white/60 hover:text-amber-400 transition-colors">
                                {{ $section->label() }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>

    {{-- ── Bottom bar ─────────────────────────────────────────────────── --}}
    <div class="border-t border-white/10">
        <div class="container mx-auto max-w-7xl px-4 py-4
                    flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-white/30">
            <span>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
            <div class="flex items-center gap-4">
                <a href="#" class="hover:text-white/60 transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-white/60 transition-colors">Terms of Use</a>
                <a href="#" class="hover:text-white/60 transition-colors">Contact</a>
            </div>
        </div>
    </div>
</footer>
