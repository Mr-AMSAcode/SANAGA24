<div class="bg-white min-h-screen">

    {{-- Header --}}
    <div class="bg-[#0d1b4b] text-white py-10 px-4">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-2xl font-extrabold tracking-tight">{{ __('Gallery') }}</h1>
            <p class="text-white/60 text-sm mt-1">
                {{ trans_choice(':count photo|:count photos', $this->pictures->total(), ['count' => $this->pictures->total()]) }}
                ·
                {{ trans_choice(':count video|:count videos', $this->videos->total(), ['count' => $this->videos->total()]) }}
            </p>

            {{-- Tabs --}}
            <div class="mt-5 flex gap-2">
                <button wire:click="setTab('photos')" type="button"
                        @class([
                            'px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide transition-colors',
                            'bg-white text-[#0d1b4b]' => $tab === 'photos',
                            'bg-white/10 text-white/70 hover:bg-white/20' => $tab !== 'photos',
                        ])>
                    {{ __('Photos') }}
                </button>
                <button wire:click="setTab('videos')" type="button"
                        @class([
                            'px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide transition-colors',
                            'bg-white text-[#0d1b4b]' => $tab === 'videos',
                            'bg-white/10 text-white/70 hover:bg-white/20' => $tab !== 'videos',
                        ])>
                    {{ __('Videos') }}
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 md:px-8 py-8">

        @if ($tab === 'photos')
            @if ($this->pictures->isEmpty())
                <div class="text-center py-20 text-neutral-400">
                    <p class="font-bold text-lg">{{ __('No photos yet.') }}</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($this->pictures as $picture)
                        <a wire:navigate
                           href="{{ $picture->post ? route('posts.show', $picture->post) : '#' }}"
                           class="group block overflow-hidden rounded-sm aspect-square bg-neutral-100"
                           title="{{ $picture->post?->title }}">
                            <img src="{{ $picture->url }}"
                                 alt="{{ $picture->alt_text }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                 loading="lazy"/>
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">{{ $this->pictures->links() }}</div>
            @endif
        @else
            @if ($this->videos->isEmpty())
                <div class="text-center py-20 text-neutral-400">
                    <p class="font-bold text-lg">{{ __('No videos yet.') }}</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($this->videos as $video)
                        <a wire:navigate
                           href="{{ $video->post ? route('posts.show', $video->post) : '#' }}"
                           class="group relative block overflow-hidden rounded-sm aspect-video bg-neutral-900"
                           title="{{ $video->post?->title }}">
                            @if ($video->isUpload())
                                <video src="{{ $video->url }}" muted preload="metadata"
                                       class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity"></video>
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-neutral-800 to-neutral-950">
                                    <span class="text-white/40 text-[10px] font-bold uppercase tracking-widest">{{ $video->provider }}</span>
                                </div>
                            @endif

                            {{-- Play icon overlay --}}
                            <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/40 transition-colors">
                                <div class="w-10 h-10 rounded-full bg-white/90 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#0d1b4b] translate-x-0.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">{{ $this->videos->links() }}</div>
            @endif
        @endif

    </div>

</div>
