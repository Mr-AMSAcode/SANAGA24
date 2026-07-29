@php
    use App\Enums\PostSection;

    /*
     * Post card partial.
     *
     * $variant (string, optional):
     *   'standard' → image top, meta below — default, used in grids
     *   'hero'     → large image, full meta block below
     *   'mini'     → image right, text left (compact list row)
     *
     * $post — App\Models\Post (with pictures, editor, stats eager-loaded)
     */
    $variant  = $variant ?? 'standard';
    $featured = $post->pictures->firstWhere('is_featured', true)
             ?? $post->pictures->first();

    $readingTime = max(1, (int) ceil(
        str_word_count(strip_tags($post->content ?? '')) / 200
    ));

    /*
     * Pill colours — keyed by PostSection enum values only.
     * PostSection cases: politics, sports, culture, science, opinion, world
     */
    $sectionColors = [
        PostSection::Politics->value => 'bg-[#0d1b4b] text-white',
        PostSection::Sports->value   => 'bg-emerald-700 text-white',
        PostSection::Culture->value  => 'bg-amber-600 text-white',
        PostSection::Science->value  => 'bg-cyan-700 text-white',
        PostSection::Opinion->value  => 'bg-rose-700 text-white',
        PostSection::World->value    => 'bg-blue-700 text-white',
    ];

    $pillClass = $sectionColors[$post->section->value ?? ''] ?? 'bg-neutral-700 text-white';
@endphp

{{-- ───────────────────────────────────────────────────────────
     HERO variant — large image, full meta + excerpt below
─────────────────────────────────────────────────────────── --}}
@if ($variant === 'hero')
    <article class="group relative overflow-hidden">
        {{-- Image --}}
        <a wire:navigate href="{{ route('posts.show', $post) }}" class="block overflow-hidden rounded-sm">
            @if ($featured)
                <div class="aspect-[16/10] overflow-hidden">
                    <img src="{{ $featured->url }}"
                         alt="{{ $featured->alt_text }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                         loading="lazy"/>
                </div>
            @else
                <div class="aspect-[16/10] bg-neutral-200 flex items-center justify-center rounded-sm">
                    <x-placeholder-pattern class="w-full h-full stroke-neutral-400/30"/>
                </div>
            @endif
        </a>

        {{-- Meta --}}
        <div class="pt-4">
            <div class="flex items-center gap-2 mb-2 text-xs text-neutral-500">
                <span class="{{ $pillClass }} text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-sm">
                    {{ $post->section->label() }}
                </span>
                <span>{{ $post->created_at->format('F j, Y') }}</span>
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                    </svg>
                    {{ $readingTime }} min read
                </span>
            </div>

            <h2 class="text-xl font-extrabold text-neutral-900 leading-snug mb-2
                       group-hover:text-[#0d1b4b] transition-colors">
                <a wire:navigate href="{{ route('posts.show', $post) }}">{{ $post->title }}</a>
            </h2>

            <p class="text-sm text-neutral-600 line-clamp-3 leading-relaxed">
                {{ Str::limit(strip_tags($post->content ?? ''), 180) }}
            </p>
        </div>
    </article>

{{-- ───────────────────────────────────────────────────────────
     MINI variant — image right, text left (list row)
─────────────────────────────────────────────────────────── --}}
@elseif ($variant === 'mini')
    <article class="group flex gap-4 py-4 border-b border-neutral-100 last:border-0">
        {{-- Text --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1.5 text-xs text-neutral-500">
                <span class="{{ $pillClass }} text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-sm">
                    {{ $post->section->label() }}
                </span>
                <span>{{ $post->created_at->format('M j, Y') }}</span>
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                    </svg>
                    {{ $readingTime }} min
                </span>
            </div>

            <h3 class="font-extrabold text-neutral-900 text-sm leading-snug mb-1.5
                       group-hover:text-[#0d1b4b] transition-colors line-clamp-3">
                <a wire:navigate href="{{ route('posts.show', $post) }}">{{ $post->title }}</a>
            </h3>

            <p class="text-xs text-neutral-500 line-clamp-2 leading-relaxed">
                {{ Str::limit(strip_tags($post->content ?? ''), 100) }}
            </p>
        </div>

        {{-- Thumbnail --}}
        @if ($featured)
            <a wire:navigate href="{{ route('posts.show', $post) }}"
               class="flex-shrink-0 w-28 h-20 overflow-hidden rounded-sm">
                <img src="{{ $featured->url }}"
                     alt="{{ $featured->alt_text }}"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                     loading="lazy"/>
            </a>
        @endif
    </article>

{{-- ───────────────────────────────────────────────────────────
     STANDARD variant — image top, meta below (grid card)
─────────────────────────────────────────────────────────── --}}
@else
    <article class="group">
        {{-- Image --}}
        <a wire:navigate href="{{ route('posts.show', $post) }}" class="block overflow-hidden rounded-sm mb-3">
            @if ($featured)
                <div class="aspect-[16/9] overflow-hidden">
                    <img src="{{ $featured->url }}"
                         alt="{{ $featured->alt_text }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                         loading="lazy"/>
                </div>
            @else
                <div class="aspect-[16/9] bg-neutral-100 flex items-center justify-center">
                    <x-placeholder-pattern class="w-full h-full stroke-neutral-300/50"/>
                </div>
            @endif
        </a>

        {{-- Meta --}}
        <div class="flex items-center gap-2 mb-2 text-xs text-neutral-500">
            <span class="{{ $pillClass }} text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-sm">
                {{ $post->section->label() }}
            </span>
            <span>{{ $post->created_at->format('M j, Y') }}</span>
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                </svg>
                {{ $readingTime }} min read
            </span>
        </div>

        <h3 class="font-extrabold text-neutral-900 leading-snug mb-2
                   group-hover:text-[#0d1b4b] transition-colors line-clamp-3">
            <a wire:navigate href="{{ route('posts.show', $post) }}">{{ $post->title }}</a>
        </h3>

        <p class="text-sm text-neutral-600 line-clamp-2 leading-relaxed">
            {{ Str::limit(strip_tags($post->content ?? ''), 120) }}
        </p>
    </article>
@endif
