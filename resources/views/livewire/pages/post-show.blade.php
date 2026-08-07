<div class="bg-white min-h-screen">

    <main class="max-w-5xl mx-auto px-4 md:px-8 py-8">

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-10">

            {{-- ── ARTICLE COLUMN ───────────────────────── --}}
            <article>
                {{-- Section pill + meta --}}
                @php
                    $sectionColors = ['politics'=>'bg-[#0d1b4b]','sports'=>'bg-emerald-700','culture'=>'bg-amber-600','science'=>'bg-cyan-700','opinion'=>'bg-rose-700','world'=>'bg-blue-700','actualite'=>'bg-indigo-700'];
                    $pillClass = ($sectionColors[$post->section->value ?? ''] ?? 'bg-[#0d1b4b]') . ' text-white';
                    $readingTime = max(1, (int) ceil(str_word_count(strip_tags($post->content ?? '')) / 200));
                @endphp

                <div class="flex flex-wrap items-center gap-3 mb-4 text-sm text-neutral-500">
                <span class="{{ $pillClass }} text-[11px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-sm">
                    {{ __($post->section->label()) }}
                </span>
                    <time datetime="{{ $post->created_at->toIso8601String() }}">
                        {{ $post->created_at->isoFormat('LL') }}
                    </time>
                    <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                    </svg>
                    {{ $readingTime }} {{ __('min read') }}
                </span>
                    <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    {{ number_format($post->stats?->view_count ?? 0) }}
                </span>

                    @can('update', $post)
                        <a wire:navigate href="{{ route('editor.posts.edit', $post) }}"
                            class="ml-auto flex items-center gap-1 text-xs font-semibold text-neutral-400
                                hover:text-[#0d1b4b] transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            {{ __('Edit') }}
                        </a>
                    @endcan
                </div>

                {{-- Title --}}
                <h1 class="text-3xl md:text-4xl font-extrabold text-neutral-900 leading-tight mb-5">
                    {{ $post->title }}
                </h1>

                {{-- Author byline --}}
                <div class="flex items-center gap-3 pb-5 mb-5 border-b border-neutral-100">
                    <div class="w-9 h-9 rounded-full bg-[#0d1b4b] text-white flex items-center justify-center
                            text-sm font-bold uppercase flex-shrink-0">
                        {{ substr($post->editor->name ?? '?', 0, 1) }}
                    </div>
                    <div>
                        @if ($post->editor)
                            <a wire:navigate href="{{ route('authors.show', $post->editor) }}"
                               class="text-sm font-bold text-neutral-800 hover:text-[#0d1b4b] transition-colors">
                                {{ $post->editor->name }}
                            </a>
                        @else
                            <p class="text-sm font-bold text-neutral-800">{{ __('Unknown') }}</p>
                        @endif
                        <p class="text-xs text-neutral-400">
                            {{ __(':date at :time', ['date' => $post->created_at->isoFormat('LL'), 'time' => $post->created_at->isoFormat('LT')]) }}
                        </p>
                    </div>
                    {{-- Share icons --}}
                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-xs text-neutral-400 font-medium">{{ __('Share:') }}</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}"
                            target="_blank"
                            class="w-7 h-7 bg-[#1877F2] rounded-full flex items-center justify-center hover:opacity-80 transition-opacity">
                            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                            </svg>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->title) }}"
                            target="_blank"
                            class="w-7 h-7 bg-neutral-900 rounded-full flex items-center justify-center hover:opacity-80 transition-opacity">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                        <a href="https://wa.me/?text={{ urlencode($post->title . ' ' . request()->url()) }}"
                            target="_blank"
                            class="w-7 h-7 bg-[#25D366] rounded-full flex items-center justify-center hover:opacity-80 transition-opacity">
                            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.978-1.306A9.956 9.956 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Featured image --}}
                @php $featured = $post->pictures->firstWhere('is_featured', true) ?? null; @endphp
                @if ($featured)
                    <figure class="mb-6">
                        <img src="{{ $featured->url }}" alt="{{ $featured->alt_text }}"
                                class="w-full rounded-sm object-cover max-h-[520px]"/>
                        @if ($featured->alt_text)
                            <figcaption class="text-xs text-neutral-400 mt-2 italic">{{ $featured->alt_text }}</figcaption>
                        @endif
                    </figure>
                @endif

                {{-- Body --}}
                <div class="prose prose-neutral prose-lg max-w-none leading-relaxed mb-8
                        prose-headings:font-extrabold prose-headings:text-neutral-900
                        prose-a:text-[#0d1b4b] prose-a:no-underline hover:prose-a:underline">
                    {!! nl2br(e($post->content ?? '')) !!}
                </div>

                {{-- Gallery --}}
                @php $gallery = $post->pictures->where('is_featured', false)->where('is_featured', '!=', true); @endphp
                @if ($gallery->isNotEmpty())
                    <div class="mb-8 grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach ($gallery as $pic)
                            <figure class="overflow-hidden rounded-sm">
                                <img src="{{ $pic->url }}" alt="{{ $pic->alt_text }}"
                                        class="w-full aspect-video object-cover hover:scale-105 transition-transform duration-300"
                                        loading="lazy"/>
                            </figure>
                        @endforeach
                    </div>
                @endif

                {{-- Videos --}}
                @if ($post->videos->isNotEmpty())
                    <div class="mb-8 space-y-4">
                        @foreach ($post->videos as $video)
                            <div class="relative w-full aspect-video rounded-sm overflow-hidden bg-neutral-900">
                                @if ($video->isEmbed())
                                    <iframe
                                        src="{{ $video->url }}"
                                        title="{{ $post->title }}"
                                        class="absolute inset-0 w-full h-full"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen
                                        loading="lazy"
                                    ></iframe>
                                @else
                                    <video src="{{ $video->url }}" controls preload="metadata"
                                           class="absolute inset-0 w-full h-full object-contain"></video>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Like + comment bar --}}
                <div class="flex items-center gap-4 p-4 bg-neutral-50 border border-neutral-100 rounded-sm mb-8">
                    <livewire:posts.like-button :target="$post" :key="'post-like-'.$post->id"/>
                    <span class="text-sm text-neutral-500">
                    {{ trans_choice(':count person liked this|:count people liked this', $likeCount, ['count' => $likeCount]) }}
                </span>
                    <a href="#comments" class="ml-auto flex items-center gap-1.5 text-sm text-neutral-400 hover:text-neutral-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        {{ trans_choice(':count comment|:count comments', $post->stats?->comment_count ?? 0, ['count' => $post->stats?->comment_count ?? 0]) }}
                    </a>
                </div>

                {{-- Tags / Related sections --}}
                <div class="flex flex-wrap items-center gap-2 mb-8 pb-8 border-b border-neutral-100">
                    <span class="text-xs font-bold text-neutral-500 uppercase tracking-wider">{{ __('Tags:') }}</span>
                    <a wire:navigate href="{{ route('posts.section', $post->section->value) }}"
                        class="text-xs font-semibold bg-neutral-100 hover:bg-[#0d1b4b] hover:text-white
                            text-neutral-700 px-3 py-1 rounded-sm transition-colors">
                        {{ __($post->section->label()) }}
                    </a>
                    @foreach ($post->tags as $tag)
                        <a wire:navigate href="{{ route('posts.tag', $tag) }}"
                            class="text-xs font-semibold bg-neutral-100 hover:bg-[#0d1b4b] hover:text-white
                                text-neutral-700 px-3 py-1 rounded-sm transition-colors">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>

                {{-- Comments --}}
                <livewire:posts.comment-thread :post="$post" :key="'comments-'.$post->id"/>
            </article>

            {{-- ── SIDEBAR ──────────────────────────────── --}}
            <aside class="hidden lg:block space-y-8">

                {{-- Related posts --}}
                @if ($this->relatedPosts->isNotEmpty())
                    <div>
                        <h3 class="text-base font-extrabold text-neutral-900 mb-4 pb-2 border-b-2 border-neutral-900">
                            {{ __('Related Articles') }}
                        </h3>
                        <div class="space-y-0 divide-y divide-neutral-100">
                            @foreach ($this->relatedPosts as $related)
                                @include('partials._post-card', ['post' => $related, 'variant' => 'mini'])
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Latest posts --}}
                @if ($this->latestPosts->isNotEmpty())
                    <div>
                        <h3 class="text-base font-extrabold text-neutral-900 mb-4 pb-2 border-b-2 border-neutral-900">
                            {{ __('Latest News') }}
                        </h3>
                        <div class="space-y-0 divide-y divide-neutral-100">
                            @foreach ($this->latestPosts as $latest)
                                @include('partials._post-card', ['post' => $latest, 'variant' => 'mini'])
                            @endforeach
                        </div>
                    </div>
                @endif

            </aside>

        </div>

    </main>

</div>