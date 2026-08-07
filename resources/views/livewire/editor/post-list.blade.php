    <div class="min-h-screen bg-stone-50">
        <div class="max-w-7xl mx-auto px-6 py-10">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-black text-stone-900 tracking-tight">{{ __('My Posts') }}</h1>
                    <p class="text-stone-500 text-sm mt-0.5">{{ __("Manage the articles you've written") }}</p>
                </div>
                <a wire:navigate href="{{ route('editor.posts.create') }}"
                   class="px-4 py-2 text-xs font-bold text-white bg-amber-500 hover:bg-amber-600
                          rounded-lg transition-colors">
                    {{ __('New Post') }}
                </a>
            </div>

            {{-- Flash --}}
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Filters --}}
            <div class="flex flex-wrap gap-3 mb-6">
                {{-- Search --}}
                <div class="relative flex-1 min-w-[200px]">
                <span class="absolute inset-y-0 left-3 flex items-center text-stone-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                </span>
                    <input wire:model.live.debounce.400ms="search" type="search"
                           placeholder="{{ __('Search by title…') }}"
                           class="w-full pl-9 pr-4 py-2 text-sm border border-stone-200 rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"/>
                </div>

                {{-- Status --}}
                <select wire:model.live="statusFilter"
                        class="text-sm border border-stone-200 rounded-lg px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-amber-400 text-stone-700">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($this->statuses as $s)
                        <option value="{{ $s->value }}">{{ __($s->label()) }}</option>
                    @endforeach
                </select>

                {{-- Section --}}
                <select wire:model.live="sectionFilter"
                        class="text-sm border border-stone-200 rounded-lg px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-amber-400 text-stone-700">
                    <option value="">{{ __('All sections') }}</option>
                    @foreach ($this->sections as $sec)
                        <option value="{{ $sec->value }}">{{ __($sec->label()) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Table --}}
            <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="border-b border-stone-100 text-left text-xs text-stone-500 uppercase tracking-wider bg-stone-50">
                        <th class="px-4 py-3 font-semibold">
                            <button wire:click="sort('title')" class="flex items-center gap-1 hover:text-stone-800">
                                {{ __('Title') }} @if($sortBy==='title')<span>{{ $sortDir==='asc'?'↑':'↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-semibold">{{ __('Section') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-semibold text-center">{{ __('Views') }}</th>
                        <th class="px-4 py-3 font-semibold">
                            <button wire:click="sort('created_at')" class="flex items-center gap-1 hover:text-stone-800">
                                {{ __('Date') }} @if($sortBy==='created_at')<span>{{ $sortDir==='asc'?'↑':'↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-50">
                    @forelse ($this->posts as $post)
                        <tr class="hover:bg-stone-50 transition-colors group">
                            {{-- Title --}}
                            <td class="px-4 py-3.5 max-w-xs">
                                <a wire:navigate href="{{ route('editor.posts.edit', $post) }}"
                                   class="font-semibold text-stone-800 hover:text-amber-600 transition-colors line-clamp-2">
                                    {{ $post->title }}
                                </a>
                            </td>

                            {{-- Section --}}
                            <td class="px-4 py-3.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-amber-700
                                         bg-amber-50 px-2 py-0.5 rounded-full">
                                {{ __($post->section->label()) }}
                            </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3.5">
                            <span @class([
                                'inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full',
                                'bg-green-100 text-green-700' => $post->status->value === 'published',
                                'bg-stone-100 text-stone-600' => $post->status->value === 'draft',
                                'bg-blue-50 text-blue-600'    => $post->status->value === 'scheduled',
                                'bg-red-50 text-red-600'     => $post->status->value === 'archived',
                            ])>
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ __($post->status->label()) }}
                            </span>
                            </td>

                            {{-- Views --}}
                            <td class="px-4 py-3.5 text-center text-stone-500">
                                {{ number_format($post->stats?->view_count ?? 0) }}
                            </td>

                            {{-- Date --}}
                            <td class="px-4 py-3.5 text-stone-400 text-xs whitespace-nowrap">
                                {{ $post->created_at->isoFormat('ll') }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @if ($post->status->value === 'draft')
                                        <button wire:click="publish({{ $post->id }})"
                                                title="{{ __('Publish') }}"
                                                class="p-1.5 text-green-500 hover:text-green-700 rounded-md hover:bg-green-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </button>
                                    @endif
                                    <a wire:navigate href="{{ route('editor.posts.edit', $post) }}"
                                       title="{{ __('Edit') }}"
                                       class="p-1.5 text-stone-400 hover:text-stone-700 rounded-md hover:bg-stone-100">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <button wire:click="delete({{ $post->id }})"
                                            wire:confirm="{{ __("Move ':title' to trash?", ['title' => addslashes($post->title)]) }}"
                                            title="{{ __('Trash') }}"
                                            class="p-1.5 text-red-400 hover:text-red-700 rounded-md hover:bg-red-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center text-stone-400">
                                <p class="font-semibold">{{ __('No posts match your filters.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($this->posts->hasPages())
                <div class="mt-6">{{ $this->posts->links() }}</div>
            @endif

        </div>
    </div>
