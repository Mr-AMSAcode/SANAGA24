<div class="min-h-screen bg-stone-50">
    <div class="max-w-6xl mx-auto px-6 py-10">

        <div class="mb-8">
            <h1 class="text-2xl font-black text-stone-900 tracking-tight">Admin Dashboard</h1>
            <p class="text-stone-500 text-sm mt-0.5">Site-wide overview — {{ now()->format('d F Y') }}</p>
        </div>

        {{-- Stats grid --}}
        @php $s = $this->siteStats; @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
            @foreach ([
                ['label' => 'Total Users',    'value' => number_format($s['total_users']),    'color' => 'text-stone-900',  'bg' => 'bg-white'],
                ['label' => 'Editors',        'value' => number_format($s['total_editors']),  'color' => 'text-blue-600',   'bg' => 'bg-blue-50'],
                ['label' => 'Published',      'value' => number_format($s['published']),      'color' => 'text-green-600',  'bg' => 'bg-green-50'],
                ['label' => 'Total Views',    'value' => number_format($s['total_views']),    'color' => 'text-amber-600',  'bg' => 'bg-amber-50'],
                ['label' => 'Total Posts',    'value' => number_format($s['total_posts']),    'color' => 'text-stone-900',  'bg' => 'bg-white'],
                ['label' => 'Drafts',         'value' => number_format($s['drafts']),         'color' => 'text-stone-600',  'bg' => 'bg-stone-50'],
                ['label' => 'Total Likes',    'value' => number_format($s['total_likes']),    'color' => 'text-rose-600',   'bg' => 'bg-rose-50'],
                ['label' => 'Total Comments', 'value' => number_format($s['total_comments']), 'color' => 'text-indigo-600', 'bg' => 'bg-indigo-50'],
            ] as $stat)
                <div class="rounded-xl border border-stone-100 {{ $stat['bg'] }} p-5 shadow-sm">
                    <p class="text-xs text-stone-500 font-semibold uppercase tracking-wider mb-1">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-black {{ $stat['color'] }}">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Top editors --}}
            <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-stone-100">
                    <h2 class="font-black text-stone-900">Top Editors</h2>
                    <p class="text-xs text-stone-400 mt-0.5">By published post count</p>
                </div>
                <ul class="divide-y divide-stone-50">
                    @forelse ($this->topEditors as $i => $editor)
                        <li class="px-6 py-3.5 flex items-center gap-3">
                            <span class="text-xs font-black text-stone-300 w-4">{{ $i + 1 }}</span>
                            <div class="w-8 h-8 rounded-full bg-stone-800 text-white flex items-center justify-center
                                        text-xs font-bold uppercase flex-shrink-0">
                                {{ substr($editor->name, 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-stone-800 truncate">{{ $editor->name }}</p>
                                <p class="text-xs text-stone-400 truncate">{{ $editor->email }}</p>
                            </div>
                            <span class="text-sm font-bold text-amber-600">{{ $editor->published_count }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-stone-400 text-sm">No editors yet.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Recent posts --}}
            <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-stone-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-black text-stone-900">Recent Posts</h2>
                        <p class="text-xs text-stone-400 mt-0.5">Across all editors</p>
                    </div>
                    <a wire:navigate href="{{ route('admin.posts') }}"
                       class="text-xs text-amber-600 hover:underline font-semibold">View all →</a>
                </div>
                <ul class="divide-y divide-stone-50">
                    @forelse ($this->recentPosts as $post)
                        <li class="px-6 py-3.5 flex items-start gap-3">
                            <span @class([
                                'mt-1 w-2 h-2 rounded-full flex-shrink-0',
                                'bg-green-400' => $post->status->value === 'published',
                                'bg-stone-300' => $post->status->value === 'draft',
                                'bg-red-300'   => $post->status->value === 'archived',
                            ])></span>
                            <div class="flex-1 min-w-0">
                                <a wire:navigate href="{{ route('editor.posts.edit', $post) }}"
                                   class="text-sm font-semibold text-stone-800 hover:text-amber-600 line-clamp-1 transition-colors">
                                    {{ $post->title }}
                                </a>
                                <p class="text-xs text-stone-400">
                                    {{ $post->editor->name ?? '—' }} · {{ $post->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-stone-400 text-sm">No posts yet.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Recent users --}}
            <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-stone-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-black text-stone-900">New Members</h2>
                        <p class="text-xs text-stone-400 mt-0.5">Recently registered</p>
                    </div>
                    <a wire:navigate href="{{ route('admin.users') }}"
                       class="text-xs text-amber-600 hover:underline font-semibold">Manage →</a>
                </div>
                <ul class="divide-y divide-stone-50">
                    @forelse ($this->recentUsers as $user)
                        <li class="px-6 py-3.5 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-stone-200 flex items-center justify-center
                                        text-xs font-bold text-stone-600 uppercase flex-shrink-0">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-stone-800 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-stone-400 truncate">{{ $user->email }}</p>
                            </div>
                            @php $role = $user->roles->first()?->name ?? 'user'; @endphp
                            <span @class([
                                'text-xs font-semibold px-2 py-0.5 rounded-full',
                                'bg-purple-100 text-purple-700' => $role === 'admin',
                                'bg-blue-100 text-blue-700'   => $role === 'editor',
                                'bg-stone-100 text-stone-500' => $role === 'user',
                            ])>
                                {{ ucfirst($role) }}
                            </span>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-stone-400 text-sm">No users yet.</li>
                    @endforelse
                </ul>
            </div>

        </div>

    </div>
</div>
