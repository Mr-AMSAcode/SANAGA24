    <div class="bg-white min-h-screen">

        {{-- Author header --}}
        <div class="bg-[#0d1b4b] text-white py-10 px-4">
            <div class="max-w-5xl mx-auto flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-amber-400 text-[#0d1b4b] flex items-center justify-center
                            text-2xl font-black uppercase flex-shrink-0">
                    {{ substr($author->name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">{{ $author->name }}</h1>
                    <p class="text-white/60 text-sm mt-1">
                        {{ trans_choice(':count published article|:count published articles', $this->publishedCount, ['count' => $this->publishedCount]) }}
                        {{ __('· Sanaga24 since :year', ['year' => $author->created_at->format('Y')]) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 md:px-8 py-8">
            @if ($this->posts->isEmpty())
                <div class="text-center py-20 text-neutral-400">
                    <p class="font-bold text-lg">{{ __('No articles yet.') }}</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    @foreach ($this->posts as $post)
                        @include('partials._post-card', ['post' => $post, 'variant' => 'standard'])
                    @endforeach
                </div>
                {{ $this->posts->links() }}
            @endif
        </div>

    </div>
