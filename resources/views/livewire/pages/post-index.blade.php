<x-layouts::app :title="$this->currentSectionLabel . ' — Sanaga24'">

    <div class="bg-white min-h-screen">

        {{-- Section header banner --}}
        <div class="bg-[#0d1b4b] text-white py-8 px-4">
            <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight">{{ $this->currentSectionLabel }}</h1>
                    <p class="text-white/60 text-sm mt-1">{{ $this->posts->total() }} articles</p>
                </div>
                {{-- Sort tabs --}}
                <div class="flex items-center gap-1 bg-white/10 rounded-sm p-1">
                    @foreach (['latest' => 'Latest', 'popular' => 'Most Viewed', 'commented' => 'Most Discussed'] as $val => $label)
                        <button wire:click="$set('sort', '{{ $val }}')"
                            @class([
                                'px-4 py-1.5 text-xs font-bold rounded-sm transition-colors',
                                'bg-white text-[#0d1b4b]' => $sort === $val,
                                'text-white/70 hover:text-white' => $sort !== $val,
                            ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Section nav tabs --}}
        <div class="border-b border-neutral-200 bg-white sticky top-0 z-20 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 md:px-8">
                <nav class="flex items-center overflow-x-auto scrollbar-hide -mb-px">
                    <a wire:navigate href="{{ route('posts.index') }}"
                        @class([
                            'px-4 py-3.5 text-sm font-bold whitespace-nowrap border-b-2 transition-colors',
                            'border-[#0d1b4b] text-[#0d1b4b]' => $section === '',
                            'border-transparent text-neutral-500 hover:text-neutral-800' => $section !== '',
                        ])>
                        All News
                    </a>
                    @foreach ($this->sections as $sec)
                        <a wire:navigate href="{{ route('posts.section', $sec->value) }}"
                            @class([
                                'px-4 py-3.5 text-sm font-bold whitespace-nowrap border-b-2 transition-colors',
                                'border-[#0d1b4b] text-[#0d1b4b]' => $section === $sec->value,
                                'border-transparent text-neutral-500 hover:text-neutral-800' => $section !== $sec->value,
                            ])>
                            {{ $sec->label() }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 md:px-8 py-8">

            {{-- Search --}}
            <div class="relative max-w-md mb-8">
            <span class="absolute inset-y-0 left-3 flex items-center text-neutral-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
            </span>
                <input wire:model.live.debounce.400ms="search" type="search"
                       placeholder="Search articles…"
                       class="w-full pl-10 pr-4 py-2.5 text-sm border border-neutral-200
                          focus:outline-none focus:ring-2 focus:ring-[#0d1b4b] rounded-sm bg-white"/>
            </div>

            <div wire:loading.class="opacity-60 pointer-events-none">

                @if ($this->posts->isEmpty())
                    <div class="text-center py-20 text-neutral-400">
                        <p class="font-bold text-lg">No articles found</p>
                        <p class="text-sm mt-1">Try a different section or search term.</p>
                    </div>
                @else
                    @if ($this->posts->onFirstPage() && ! $search)
                        <div class="mb-8">
                            @include('partials._post-card', ['post' => $this->posts->first(), 'variant' => 'hero'])
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            @foreach ($this->posts->skip(1) as $post)
                                @include('partials._post-card', ['post' => $post, 'variant' => 'standard'])
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            @foreach ($this->posts as $post)
                                @include('partials._post-card', ['post' => $post, 'variant' => 'standard'])
                            @endforeach
                        </div>
                    @endif
                    {{ $this->posts->links() }}
                @endif

            </div>
        </div>

    </div>

</x-layouts::app>
