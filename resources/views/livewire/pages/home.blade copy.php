<div class="min-h-screen bg-[#f5f4f0]">

    {{-- ═══════════════════════════════════════════════════════════
         TRENDING TICKER
    ═══════════════════════════════════════════════════════════ --}}
    @if ($this->trendingPosts->isNotEmpty())
    <div class="bg-[#0d1b4b] text-white text-xs overflow-hidden">
        <div class="container mx-auto flex items-stretch">
            <span class="flex-shrink-0 bg-amber-400 text-[#0d1b4b] font-black uppercase tracking-widest text-[11px] px-4 flex items-center">
                Trending
            </span>
            <div class="overflow-hidden flex-1 relative">
                <div class="flex animate-ticker whitespace-nowrap py-2 gap-0">
                    @foreach ($this->trendingPosts as $tp)
                    <a wire:navigate href="{{ route('posts.show', $tp) }}"
                        class="inline-flex items-center gap-2 px-5 hover:text-amber-400 transition-colors">
                        <span class="text-amber-400 font-black">›</span>
                        <span class="font-medium">{{ Str::limit($tp->title, 70) }}</span>
                    </a>
                    @endforeach
                    {{-- Duplicate for seamless loop --}}
                    @foreach ($this->trendingPosts as $tp)
                    <a wire:navigate href="{{ route('posts.show', $tp) }}"
                        class="inline-flex items-center gap-2 px-5 hover:text-amber-400 transition-colors">
                        <span class="text-amber-400 font-black">›</span>
                        <span class="font-medium">{{ Str::limit($tp->title, 70) }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="container mx-auto px-4 py-8 max-w-7xl space-y-14">

        {{-- ═══════════════════════════════════════════════════════════
             HERO SECTION — latest 3 posts carousel-style
        ═══════════════════════════════════════════════════════════ --}}
        @if ($this->heroPosts->isNotEmpty())
        <section aria-label="Featured Stories">
            @php $hero = $this->heroPosts->first(); $side = $this->heroPosts->skip(1); @endphp
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-1">

                {{-- Main hero --}}
                @include('partials._post-card', ['post' => $hero, 'variant' => 'hero'])

                {{-- Side stack --}}
                <div class="flex flex-col gap-1">
                    @foreach ($side as $sidePost)
                    @php
                    $feat = $sidePost->pictures->firstWhere('is_featured', true) ?? $sidePost->pictures->first();
                    $rt = max(1, (int) ceil(str_word_count(strip_tags($sidePost->content ?? '')) / 200));
                    $sectionColors = [
                    'politics' => 'bg-[#0d1b4b] text-white',
                    'crime' => 'bg-red-700 text-white',
                    'sports' => 'bg-emerald-700 text-white',
                    'business' => 'bg-blue-700 text-white',
                    'education' => 'bg-purple-700 text-white',
                    'culture' => 'bg-amber-600 text-white',
                    'health' => 'bg-teal-700 text-white',
                    'technology'=> 'bg-cyan-700 text-white',
                    ];
                    $pillClass = $sectionColors[$sidePost->section->value ?? ''] ?? 'bg-[#0d1b4b] text-white';
                    @endphp
                    <article class="group relative overflow-hidden bg-white flex-1 flex flex-col">
                        @if ($feat)
                        <a wire:navigate href="{{ route('posts.show', $sidePost) }}" class="block overflow-hidden">
                            <div class="aspect-[16/8] overflow-hidden">
                                <img src="{{ $feat->url }}" alt="{{ $feat->alt_text }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    loading="lazy" />
                            </div>
                        </a>
                        @else
                        <div class="aspect-[16/8] bg-neutral-200"></div>
                        @endif
                        <div class="p-4 flex-1 flex flex-col justify-center">
                            <div class="flex items-center gap-2 mb-1.5 text-xs text-neutral-500">
                                <span class="{{ $pillClass }} text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-sm">
                                    {{ $sidePost->section->label() }}
                                </span>
                                <span>{{ $sidePost->created_at->format('M j, Y') }}</span>
                            </div>
                            <h3 class="font-extrabold text-neutral-900 leading-snug group-hover:text-[#0d1b4b] transition-colors line-clamp-2">
                                <a wire:navigate href="{{ route('posts.show', $sidePost) }}">{{ $sidePost->title }}</a>
                            </h3>
                        </div>
                    </article>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- ═══════════════════════════════════════════════════════════
             RECENT NEWS — latest 4 posts from any section
        ═══════════════════════════════════════════════════════════ --}}
        @if ($this->recentSection->isNotEmpty())
        <section aria-label="Recent News">
            <div class="flex items-center gap-4 mb-6">
                <div class="h-5 w-1 bg-amber-400 rounded-full"></div>
                <h2 class="text-lg font-black uppercase tracking-widest text-[#0d1b4b]">Recent News</h2>
                <div class="flex-1 h-px bg-neutral-300"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($this->recentSection as $post)
                @include('partials._post-card', ['post' => $post, 'variant' => 'standard'])
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══════════════════════════════════════════════════════════
             SECTION BLOCKS — per-section posts with dynamic layouts
        ═══════════════════════════════════════════════════════════ --}}
        @foreach ($this->sectionBlocks as $block)
        <section aria-label="{{ $block['label'] }}">

            {{-- Section Header --}}
            <div class="flex items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="h-6 w-1 bg-[#0d1b4b] rounded-full"></div>
                    <h2 class="text-lg font-black uppercase tracking-widest text-[#0d1b4b]">
                        {{ $block['label'] }}
                    </h2>
                </div>
                <div class="flex-1 h-px bg-neutral-300"></div>
                <a wire:navigate href="{{ route($block['slug']) }}"
                    class="flex-shrink-0 text-xs font-bold uppercase tracking-widest text-[#0d1b4b]
                              border border-[#0d1b4b] px-3 py-1 rounded-sm
                              hover:bg-[#0d1b4b] hover:text-white transition-colors">
                    All {{ $block['label'] }} →
                </a>
            </div>

            {{-- four-col layout --}}
            @if ($block['layout'] === 'four-col')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($block['posts'] as $post)
                @include('partials._post-card', ['post' => $post, 'variant' => 'standard'])
                @endforeach
            </div>

            {{-- three-col layout --}}
            @elseif ($block['layout'] === 'three-col')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($block['posts'] as $post)
                @include('partials._post-card', ['post' => $post, 'variant' => 'standard'])
                @endforeach
            </div>

            {{-- list layout --}}
            @elseif ($block['layout'] === 'list')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 divide-y divide-neutral-100 lg:divide-y-0">
                <div class="divide-y divide-neutral-100">
                    @foreach ($block['posts']->take(ceil($block['posts']->count() / 2)) as $post)
                    @include('partials._post-card', ['post' => $post, 'variant' => 'mini'])
                    @endforeach
                </div>
                <div class="divide-y divide-neutral-100">
                    @foreach ($block['posts']->skip(ceil($block['posts']->count() / 2)) as $post)
                    @include('partials._post-card', ['post' => $post, 'variant' => 'mini'])
                    @endforeach
                </div>
            </div>

            {{-- hero-mini layout: 1 hero + mini list --}}
            @elseif ($block['layout'] === 'hero-mini')
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8">
                {{-- Hero card --}}
                <div>
                    @include('partials._post-card', ['post' => $block['posts']->first(), 'variant' => 'hero'])
                </div>
                {{-- Mini list --}}
                <div class="divide-y divide-neutral-100">
                    @foreach ($block['posts']->skip(1) as $post)
                    @include('partials._post-card', ['post' => $post, 'variant' => 'mini'])
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Load More --}}
            <div class="mt-6 text-center">
                <button
                    wire:click="loadMoreSection('{{ $block['slug'] }}')"
                    wire:loading.attr="disabled"
                    wire:target="loadMoreSection('{{ $block['slug'] }}')"
                    class="inline-flex items-center gap-2 px-6 py-2.5 border-2 border-[#0d1b4b]
                               text-[#0d1b4b] text-xs font-black uppercase tracking-widest rounded-sm
                               hover:bg-[#0d1b4b] hover:text-white transition-colors
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="loadMoreSection('{{ $block['slug'] }}')">
                        Load More {{ $block['label'] }}
                    </span>
                    <span wire:loading wire:target="loadMoreSection('{{ $block['slug'] }}')">
                        Loading…
                    </span>
                </button>
            </div>

        </section>
        @endforeach

    </div>{{-- /container --}}

    {{-- Ticker animation --}}
    <style>
        @keyframes ticker {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        .animate-ticker {
            animation: ticker 35s linear infinite;
        }

        .animate-ticker:hover {
            animation-play-state: paused;
        }
    </style>

</div>