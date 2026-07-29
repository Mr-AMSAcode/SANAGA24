@php use App\Enums\PostSection; @endphp

<div class="w-full bg-[#0d1b4b] text-white shadow-lg">

    {{-- ── Top bar: date + auth actions ───────────────────────────────── --}}
    <div class="border-b border-white/10">
        <div class="container mx-auto max-w-7xl px-4 flex items-center justify-between h-8 text-[11px] text-white/50">
            <span class="flex items-center gap-1.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                {{ now()->isoFormat('dddd, MMMM D, YYYY') }}
            </span>

            <div class="flex items-center gap-4">
                @auth
                    @if (auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('admin.dashboard') }}"
                           class="text-purple-300 hover:text-purple-200 transition-colors font-semibold">
                            Admin
                        </a>
                    @endif
                    @if (auth()->user()->hasRole('editor') || auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('editor.dashboard') }}"
                           class="text-amber-300 hover:text-amber-200 transition-colors font-semibold">
                            Editor
                        </a>
                    @endif
                @else
                    <a wire:navigate href="{{ route('login') }}"
                       class="hover:text-white transition-colors">Sign in</a>
                    <a wire:navigate href="{{ route('register') }}"
                       class="text-amber-400 hover:text-amber-300 font-semibold transition-colors">Register</a>
                @endauth
            </div>
        </div>
    </div>

    {{-- ── Main header: logo + desktop nav + user menu ─────────────────── --}}
    <div class="container mx-auto max-w-7xl px-4">
        <div class="flex items-center justify-between h-16">

            {{-- Logo --}}
            <a wire:navigate href="{{ route('home') }}" class="flex-shrink-0">
                <x-app-logo-icon class="h-10 w-auto brightness-0 invert" />
            </a>

            {{-- Desktop navigation — driven by PostSection enum --}}
            <nav class="hidden lg:flex items-stretch h-full gap-0.5" aria-label="Main navigation">
                <a wire:navigate href="{{ route('home') }}"
                   class="flex items-center px-4 text-sm font-semibold tracking-wide transition-colors
                          hover:bg-white/10
                          {{ request()->routeIs('home')
                              ? 'text-amber-400 border-b-2 border-amber-400'
                              : 'text-white/80 border-b-2 border-transparent' }}">
                    Home
                </a>

                @foreach (PostSection::cases() as $section)
                    <a wire:navigate href="{{ route($section->value) }}"
                       class="flex items-center px-4 text-sm font-semibold tracking-wide transition-colors
                              hover:bg-white/10
                              {{ request()->routeIs($section->value)
                                  ? 'text-amber-400 border-b-2 border-amber-400'
                                  : 'text-white/80 border-b-2 border-transparent' }}">
                        {{ $section->label() }}
                    </a>
                @endforeach
            </nav>

            {{-- Right side: auth avatar / mobile toggle --}}
            <div class="flex items-center gap-3">

                {{-- Authenticated user dropdown (desktop) --}}
                @auth
                    <div class="hidden lg:block relative group">
                        <button class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-amber-400 flex items-center justify-center
                                        text-[11px] font-black text-[#0d1b4b] uppercase">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <svg class="w-3 h-3 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-xl
                                    border border-gray-100 py-1 z-50
                                    invisible opacity-0 group-hover:visible group-hover:opacity-100
                                    transition-all duration-150">
                            <div class="px-3 py-2 border-b border-gray-100">
                                <p class="text-xs font-semibold text-gray-800 truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-gray-400 truncate">{{ auth()->user()->email }}</p>
                            </div>
                            <a wire:navigate href="{{ route('profile.edit') }}"
                               class="flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Profile & Settings
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth

                {{-- Mobile hamburger --}}
                <button
                    x-data
                    x-on:click="$dispatch('toggle-mobile-menu')"
                    class="lg:hidden p-2 rounded-md text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                    aria-label="Toggle menu"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>

        </div>
    </div>

    {{-- ── Mobile menu (Alpine-driven) ────────────────────────────────── --}}
    <div
        x-data="{ open: false }"
        x-on:toggle-mobile-menu.window="open = !open"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="lg:hidden border-t border-white/10 bg-[#0d1b4b]"
        style="display: none"
    >
        <div class="container mx-auto max-w-7xl px-4 py-4 space-y-1">

            <a wire:navigate href="{{ route('home') }}"
               class="block px-3 py-2 text-sm rounded-md transition-colors
                      {{ request()->routeIs('home') ? 'bg-white/10 text-amber-400' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                Home
            </a>

            {{-- All sections from enum --}}
            @foreach (PostSection::cases() as $section)
                <a wire:navigate href="{{ route($section->value) }}"
                   class="block px-3 py-2 text-sm rounded-md transition-colors
                          {{ request()->routeIs($section->value) ? 'bg-white/10 text-amber-400' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    {{ $section->label() }}
                </a>
            @endforeach

            {{-- Auth actions in mobile --}}
            @auth
                <div class="border-t border-white/10 pt-3 mt-3 space-y-1">
                    <div class="px-3 py-1.5 text-xs text-white/40 uppercase tracking-widest font-semibold">
                        Account
                    </div>
                    <a wire:navigate href="{{ route('profile.edit') }}"
                       class="block px-3 py-2 text-sm text-white/80 hover:bg-white/10 hover:text-white rounded-md transition-colors">
                        Profile & Settings
                    </a>
                    @if (auth()->user()->hasRole('editor') || auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('editor.dashboard') }}"
                           class="block px-3 py-2 text-sm text-amber-400 hover:bg-white/10 rounded-md transition-colors">
                            Editor Panel
                        </a>
                    @endif
                    @if (auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('admin.dashboard') }}"
                           class="block px-3 py-2 text-sm text-purple-400 hover:bg-white/10 rounded-md transition-colors">
                            Admin Panel
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-white/10 rounded-md transition-colors">
                            Log Out
                        </button>
                    </form>
                </div>
            @else
                <div class="border-t border-white/10 pt-3 mt-3 flex gap-2">
                    <a wire:navigate href="{{ route('login') }}"
                       class="flex-1 text-center px-3 py-2 text-sm border border-white/20 rounded-md
                              text-white/80 hover:bg-white/10 transition-colors">
                        Sign in
                    </a>
                    <a wire:navigate href="{{ route('register') }}"
                       class="flex-1 text-center px-3 py-2 text-sm bg-amber-400 text-[#0d1b4b]
                              font-black rounded-md hover:bg-amber-300 transition-colors">
                        Register
                    </a>
                </div>
            @endauth

        </div>
    </div>

</div>
