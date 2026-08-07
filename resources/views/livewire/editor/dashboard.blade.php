<div class="min-h-screen bg-stone-50">
    <div class="max-w-5xl mx-auto px-6 py-10">

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black text-stone-900 tracking-tight">
                    {{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}
                </h1>
                <p class="text-stone-500 text-sm mt-0.5">{{ __("Here's how your content is performing") }}</p>
            </div>
            <a wire:navigate href="{{ route('editor.posts.create') }}"
               class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-500
                      text-stone-900 font-bold px-5 py-2.5 rounded-lg text-sm shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('New Post') }}
            </a>
        </div>

        {{-- Stats cards --}}
        @php $s = $this->stats; @endphp
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-10">
            @foreach ([
                ['label' => __('Published'),  'value' => $s['published'],      'color' => 'text-green-600',  'bg' => 'bg-green-50'],
                ['label' => __('Scheduled'),  'value' => $s['scheduled'],      'color' => 'text-blue-600',   'bg' => 'bg-blue-50'],
                ['label' => __('Drafts'),     'value' => $s['drafts'],         'color' => 'text-stone-700',  'bg' => 'bg-white'],
                ['label' => __('Total Views'),'value' => number_format($s['total_views']),  'color' => 'text-amber-600',  'bg' => 'bg-amber-50'],
                ['label' => __('Total Likes'),'value' => number_format($s['total_likes']),  'color' => 'text-rose-600',   'bg' => 'bg-rose-50'],
            ] as $stat)
                <div class="rounded-xl border border-stone-100 {{ $stat['bg'] }} p-5 shadow-sm">
                    <p class="text-xs text-stone-500 font-semibold uppercase tracking-wider mb-1">{{ $stat['label'] }}</p>
                    <p class="text-3xl font-black {{ $stat['color'] }}">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Top performing posts --}}
            <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-stone-100">
                    <h2 class="font-black text-stone-900">{{ __('Top Posts') }}</h2>
                    <p class="text-xs text-stone-400 mt-0.5">{{ __('By view count') }}</p>
                </div>
                <ul class="divide-y divide-stone-50">
                    @forelse ($this->topPosts as $i => $post)
                        <li class="px-6 py-3.5 flex items-start gap-3">
                            <span class="text-lg font-black text-stone-200 leading-none w-5 flex-shrink-0">
                                {{ $i + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <a wire:navigate href="{{ route('posts.show', $post) }}"
                                   class="text-sm font-semibold text-stone-800 hover:text-amber-600
                                          transition-colors line-clamp-1">
                                    {{ $post->title }}
                                </a>
                                <p class="text-xs text-stone-400 mt-0.5">
                                    {{ trans_choice(':count view|:count views', $post->stats?->view_count ?? 0, ['count' => number_format($post->stats?->view_count ?? 0)]) }} ·
                                    {{ trans_choice(':count like|:count likes', $post->stats?->like_count ?? 0, ['count' => $post->stats?->like_count ?? 0]) }}
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-stone-400 text-sm">
                            {{ __('No published posts yet.') }}
                        </li>
                    @endforelse
                </ul>
            </div>

            {{-- Recent posts --}}
            <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-stone-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-black text-stone-900">{{ __('Recent Activity') }}</h2>
                        <p class="text-xs text-stone-400 mt-0.5">{{ __('Your latest posts') }}</p>
                    </div>
                    <a wire:navigate href="{{ route('editor.posts') }}"
                       class="text-xs text-amber-600 hover:underline font-semibold">
                        {{ __('Manage all →') }}
                    </a>
                </div>
                <ul class="divide-y divide-stone-50">
                    @forelse ($this->recentPosts as $post)
                        <li class="px-6 py-3.5 flex items-center gap-3">
                            <span @class([
                                'w-2 h-2 rounded-full flex-shrink-0 mt-0.5',
                                'bg-green-400' => $post->status->value === 'published',
                                'bg-stone-300' => $post->status->value === 'draft',
                                'bg-red-300'   => $post->status->value === 'archived',
                            ])></span>
                            <div class="flex-1 min-w-0">
                                <a wire:navigate href="{{ route('editor.posts.edit', $post) }}"
                                   class="text-sm font-semibold text-stone-800 hover:text-amber-600
                                          transition-colors line-clamp-1">
                                    {{ $post->title }}
                                </a>
                                <p class="text-xs text-stone-400">
                                    {{ __($post->status->label()) }} · {{ $post->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-stone-400 text-sm">
                            {{ __("You haven't written anything yet.") }}
                            <a wire:navigate href="{{ route('editor.posts.create') }}"
                               class="text-amber-600 hover:underline">{{ __('Start writing →') }}</a>
                        </li>
                    @endforelse
                </ul>
            </div>

        </div>

    </div>
</div>
