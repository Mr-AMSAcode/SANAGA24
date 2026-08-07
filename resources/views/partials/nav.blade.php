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
                {{ now()->isoFormat('dddd, LL') }}
            </span>

            <div class="flex items-center gap-4">
                {{-- Language switcher — a plain link (no wire:navigate) so
                     the whole page reloads with the new locale applied. --}}
                <div class="flex items-center gap-1.5" aria-label="{{ __('Language') }}">
                    <a href="{{ route('locale.switch', 'fr') }}"
                       class="{{ app()->getLocale() === 'fr' ? 'text-white font-bold' : 'text-white/50 hover:text-white' }} transition-colors">
                        FR
                    </a>
                    <span class="text-white/20">|</span>
                    <a href="{{ route('locale.switch', 'en') }}"
                       class="{{ app()->getLocale() === 'en' ? 'text-white font-bold' : 'text-white/50 hover:text-white' }} transition-colors">
                        EN
                    </a>
                </div>

                <span class="text-white/20">•</span>

                @auth
                    @if (auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('admin.dashboard') }}"
                           class="text-purple-300 hover:text-purple-200 transition-colors font-semibold">
                            {{ __('Admin') }}
                        </a>
                    @endif
                    @if (auth()->user()->hasRole('editor') || auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('editor.dashboard') }}"
                           class="text-amber-300 hover:text-amber-200 transition-colors font-semibold">
                            {{ __('Editor') }}
                        </a>
                    @endif
                @else
                    <a wire:navigate href="{{ route('login') }}"
                       class="hover:text-white transition-colors">{{ __('Sign in') }}</a>
                    <a wire:navigate href="{{ route('register') }}"
                       class="text-amber-400 hover:text-amber-300 font-semibold transition-colors">{{ __('Register') }}</a>
                @endauth
            </div>
        </div>
    </div>

    {{-- ── Main header: logo + desktop nav + user menu ─────────────────── --}}
    <div class="container mx-auto max-w-7xl px-4">
        <div class="flex items-center justify-between h-16">

            {{-- Logo --}}
            <a wire:navigate href="{{ route('home') }}" class="flex-shrink-0">
                <x-app-logo-icon class="h-12 w-auto" />
            </a>

            {{-- Desktop navigation — driven by PostSection enum --}}
            <nav class="hidden lg:flex items-stretch h-full gap-0.5" aria-label="{{ __('Main navigation') }}">
                <a wire:navigate href="{{ route('home') }}"
                   class="flex items-center px-4 text-sm font-semibold tracking-wide transition-colors
                          hover:bg-white/10
                          {{ request()->routeIs('home')
                              ? 'text-amber-400 border-b-2 border-amber-400'
                              : 'text-white/80 border-b-2 border-transparent' }}">
                    {{ __('Home') }}
                </a>

                @foreach (PostSection::primaryNav() as $section)
                    <a wire:navigate href="{{ route($section->value) }}"
                       class="flex items-center px-4 text-sm font-semibold tracking-wide transition-colors
                              hover:bg-white/10
                              {{ request()->routeIs($section->value)
                                  ? 'text-amber-400 border-b-2 border-amber-400'
                                  : 'text-white/80 border-b-2 border-transparent' }}">
                        {{ __($section->label()) }}
                    </a>
                @endforeach

                {{-- "Autre" dropdown — the 12 secondary rubriques --}}
                <div class="relative flex items-stretch" x-data="{ open: false }" x-on:click.outside="open = false">
                    <button type="button"
                            x-on:click="open = ! open"
                            class="flex items-center gap-1 px-4 text-sm font-semibold tracking-wide transition-colors
                                   hover:bg-white/10
                                   {{ collect(PostSection::otherMenu())->contains(fn ($s) => request()->routeIs($s->value)) || request()->routeIs('galerie')
                                       ? 'text-amber-400 border-b-2 border-amber-400'
                                       : 'text-white/80 border-b-2 border-transparent' }}">
                        {{ __('Other') }}
                        <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute left-0 top-full w-64 bg-white rounded-b-xl shadow-xl border border-gray-100 py-2 z-50">
                        @foreach (PostSection::otherMenu() as $section)
                            <a wire:navigate href="{{ route($section->value) }}"
                               class="block px-4 py-2 text-sm transition-colors
                                      {{ request()->routeIs($section->value)
                                          ? 'text-amber-600 bg-amber-50 font-semibold'
                                          : 'text-gray-700 hover:bg-gray-50' }}">
                                {{ __($section->label()) }}
                            </a>
                        @endforeach
                        <div class="my-1 border-t border-gray-100"></div>
                        {{-- Not a rubrique — the site-wide media gallery --}}
                        <a wire:navigate href="{{ route('galerie') }}"
                           class="block px-4 py-2 text-sm transition-colors
                                  {{ request()->routeIs('galerie')
                                      ? 'text-amber-600 bg-amber-50 font-semibold'
                                      : 'text-gray-700 hover:bg-gray-50' }}">
                            {{ __('Gallery') }}
                        </a>
                    </div>
                </div>
            </nav>

            {{-- Right side: auth avatar / mobile toggle --}}
            <div class="flex items-center gap-3">

                {{-- Site search (desktop) --}}
                <div class="hidden lg:block relative" x-data="{ open: false }">
                    <button type="button"
                            x-on:click="open = ! open; if (open) $nextTick(() => $refs.searchInput.focus())"
                            class="p-2 text-white/70 hover:text-white transition-colors" aria-label="{{ __('Search articles') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                        </svg>
                    </button>
                    <form method="GET" action="{{ route('posts.index') }}"
                          x-show="open" x-cloak x-on:click.outside="open = false"
                          class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl
                                 border border-gray-100 p-2 z-50">
                        <input x-ref="searchInput" type="search" name="q" placeholder="{{ __('Search articles…') }}"
                               value="{{ request('q') }}"
                               class="w-full px-3 py-2 text-sm text-gray-800 border border-gray-200 rounded-lg
                                      focus:outline-none focus:ring-2 focus:ring-amber-400"/>
                    </form>
                </div>

                {{-- Notifications --}}
                @auth
                    <livewire:notification-bell/>
                @endauth

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
                                {{ __('Profile & Settings') }}
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
                    aria-label="{{ __('Toggle menu') }}"
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

            {{-- Site search (mobile) --}}
            <form method="GET" action="{{ route('posts.index') }}" class="mb-3">
                <input type="search" name="q" placeholder="{{ __('Search articles…') }}" value="{{ request('q') }}"
                       class="w-full px-3 py-2 text-sm text-white placeholder-white/40 bg-white/10 border border-white/10
                              rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400"/>
            </form>

            <a wire:navigate href="{{ route('home') }}"
               class="block px-3 py-2 text-sm rounded-md transition-colors
                      {{ request()->routeIs('home') ? 'bg-white/10 text-amber-400' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                {{ __('Home') }}
            </a>

            {{-- Primary rubriques --}}
            @foreach (PostSection::primaryNav() as $section)
                <a wire:navigate href="{{ route($section->value) }}"
                   class="block px-3 py-2 text-sm rounded-md transition-colors
                          {{ request()->routeIs($section->value) ? 'bg-white/10 text-amber-400' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    {{ __($section->label()) }}
                </a>
            @endforeach

            {{-- "Autre" — the 12 secondary rubriques --}}
            <div class="px-3 py-1.5 pt-3 text-xs text-white/40 uppercase tracking-widest font-semibold">
                {{ __('Other') }}
            </div>
            @foreach (PostSection::otherMenu() as $section)
                <a wire:navigate href="{{ route($section->value) }}"
                   class="block px-3 py-2 text-sm rounded-md transition-colors
                          {{ request()->routeIs($section->value) ? 'bg-white/10 text-amber-400' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    {{ __($section->label()) }}
                </a>
            @endforeach
            {{-- Not a rubrique — the site-wide media gallery --}}
            <a wire:navigate href="{{ route('galerie') }}"
               class="block px-3 py-2 text-sm rounded-md transition-colors
                      {{ request()->routeIs('galerie') ? 'bg-white/10 text-amber-400' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                {{ __('Gallery') }}
            </a>

            {{-- Auth actions in mobile --}}
            @auth
                <div class="border-t border-white/10 pt-3 mt-3 space-y-1">
                    <div class="px-3 py-1.5 text-xs text-white/40 uppercase tracking-widest font-semibold">
                        {{ __('Account') }}
                    </div>
                    <a wire:navigate href="{{ route('profile.edit') }}"
                       class="block px-3 py-2 text-sm text-white/80 hover:bg-white/10 hover:text-white rounded-md transition-colors">
                        {{ __('Profile & Settings') }}
                    </a>
                    @if (auth()->user()->hasRole('editor') || auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('editor.dashboard') }}"
                           class="block px-3 py-2 text-sm text-amber-400 hover:bg-white/10 rounded-md transition-colors">
                            {{ __('Editor Panel') }}
                        </a>
                    @endif
                    @if (auth()->user()->hasRole('admin'))
                        <a wire:navigate href="{{ route('admin.dashboard') }}"
                           class="block px-3 py-2 text-sm text-purple-400 hover:bg-white/10 rounded-md transition-colors">
                            {{ __('Admin Panel') }}
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-white/10 rounded-md transition-colors">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            @else
                <div class="border-t border-white/10 pt-3 mt-3 flex gap-2">
                    <a wire:navigate href="{{ route('login') }}"
                       class="flex-1 text-center px-3 py-2 text-sm border border-white/20 rounded-md
                              text-white/80 hover:bg-white/10 transition-colors">
                        {{ __('Sign in') }}
                    </a>
                    <a wire:navigate href="{{ route('register') }}"
                       class="flex-1 text-center px-3 py-2 text-sm bg-amber-400 text-[#0d1b4b]
                              font-black rounded-md hover:bg-amber-300 transition-colors">
                        {{ __('Register') }}
                    </a>
                </div>
            @endauth

        </div>
    </div>

</div>
