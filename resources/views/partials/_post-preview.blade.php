{{--
    Read-only rendering of an in-progress post, exactly as a visitor would
    see it once published — used by the editor/create "Preview" modal.
    Deliberately independent of the real post-show view: at this point the
    post has no id, no comments, no likes, so it can't reuse those Livewire
    sub-components. Every variable below is a plain scalar/array, not an
    Eloquent model, so it works identically for a not-yet-saved draft.

    Expected variables:
    string $title
    string $content
    string $sectionLabel
    string $sectionColorClass
    string $editorName
    string $dateLabel
    ?string $featuredImageUrl
    array<string> $galleryImageUrls
    array<array{type:string,url:string,provider:?string}> $videos
    array<string> $tagNames
    int $readingTime
--}}
<article class="bg-white max-w-2xl mx-auto px-4 md:px-8 py-8">

    <div class="flex flex-wrap items-center gap-3 mb-4 text-sm text-neutral-500">
        <span class="{{ $sectionColorClass }} text-[11px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-sm">
            {{ $sectionLabel }}
        </span>
        <time>{{ $dateLabel }}</time>
        <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke-width="2"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
            </svg>
            {{ $readingTime }} {{ __('min read') }}
        </span>
    </div>

    <h1 class="text-3xl md:text-4xl font-extrabold text-neutral-900 leading-tight mb-5">
        {{ $title !== '' ? $title : __('Article title…') }}
    </h1>

    <div class="flex items-center gap-3 pb-5 mb-5 border-b border-neutral-100">
        <div class="w-9 h-9 rounded-full bg-[#0d1b4b] text-white flex items-center justify-center
                text-sm font-bold uppercase flex-shrink-0">
            {{ substr($editorName, 0, 1) }}
        </div>
        <div>
            <p class="text-sm font-bold text-neutral-800">{{ $editorName }}</p>
            <p class="text-xs text-neutral-400">{{ $dateLabel }}</p>
        </div>
    </div>

    @if ($featuredImageUrl)
        <figure class="mb-6">
            <img src="{{ $featuredImageUrl }}" alt="" class="w-full rounded-sm object-cover max-h-[420px]"/>
        </figure>
    @endif

    <div class="prose prose-neutral max-w-none leading-relaxed mb-8
            prose-headings:font-extrabold prose-headings:text-neutral-900
            prose-a:text-[#0d1b4b] prose-a:no-underline">
        @if ($content !== '')
            {!! nl2br(e($content)) !!}
        @else
            <p class="text-neutral-300 italic">{{ __('Start writing…') }}</p>
        @endif
    </div>

    @if (count($galleryImageUrls))
        <div class="mb-8 grid grid-cols-2 md:grid-cols-3 gap-3">
            @foreach ($galleryImageUrls as $url)
                <figure class="overflow-hidden rounded-sm">
                    <img src="{{ $url }}" class="w-full aspect-video object-cover"/>
                </figure>
            @endforeach
        </div>
    @endif

    @if (count($videos))
        <div class="mb-8 space-y-4">
            @foreach ($videos as $video)
                <div class="relative w-full aspect-video rounded-sm overflow-hidden bg-neutral-900">
                    @if ($video['type'] === 'embed')
                        <iframe src="{{ $video['url'] }}" class="absolute inset-0 w-full h-full"
                                allow="accelerometer; autoplay; encrypted-media; picture-in-picture" loading="lazy"></iframe>
                    @else
                        <video src="{{ $video['url'] }}" controls preload="metadata"
                               class="absolute inset-0 w-full h-full object-contain"></video>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (count($tagNames))
        <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-neutral-100">
            <span class="text-xs font-bold text-neutral-500 uppercase tracking-wider">{{ __('Tags:') }}</span>
            <span class="text-xs font-semibold bg-neutral-100 text-neutral-700 px-3 py-1 rounded-sm">
                {{ $sectionLabel }}
            </span>
            @foreach ($tagNames as $tagName)
                <span class="text-xs font-semibold bg-neutral-100 text-neutral-700 px-3 py-1 rounded-sm">
                    #{{ $tagName }}
                </span>
            @endforeach
        </div>
    @endif

</article>
