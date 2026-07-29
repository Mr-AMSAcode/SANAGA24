<x-layouts::app :title="'All Posts — Admin'">

    <div class="min-h-screen bg-stone-50">
        <div class="max-w-7xl mx-auto px-6 py-10">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-black text-stone-900 tracking-tight">All Posts</h1>
                    <p class="text-stone-500 text-sm mt-0.5">Manage every article across all editors</p>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Trashed toggle --}}
                    <label class="flex items-center gap-2 text-sm text-stone-600 cursor-pointer select-none">
                        <div class="relative">
                            <input wire:model.live="showTrashed" type="checkbox" class="sr-only peer"/>
                            <div class="w-9 h-5 bg-stone-200 peer-checked:bg-amber-400 rounded-full transition-colors"></div>
                            <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full
                                    peer-checked:translate-x-4 transition-transform shadow-sm"></div>
                        </div>
                        Show Trashed
                    </label>
                </div>
            </div>

            {{-- Flash --}}
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Bulk action bar (visible when posts are selected) --}}
            @if (count($selected) > 0)
                <div class="mb-4 flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <span class="text-sm font-semibold text-amber-800">
                    {{ count($selected) }} post{{ count($selected) > 1 ? 's' : '' }} selected
                </span>
                    <button wire:click="bulkPublish"
                            class="px-4 py-1.5 text-xs font-bold text-white bg-green-600 hover:bg-green-700
                               rounded-lg transition-colors">
                        Bulk Publish
                    </button>
                    <button wire:click="bulkDelete"
                            wire:confirm="Move selected posts to trash?"
                            class="px-4 py-1.5 text-xs font-bold text-white bg-red-500 hover:bg-red-600
                               rounded-lg transition-colors">
                        Bulk Delete
                    </button>
                    <button wire:click="$set('selected', [])"
                            class="ml-auto text-xs text-stone-400 hover:text-stone-700 transition-colors">
                        Clear selection
                    </button>
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
                           placeholder="Search by title…"
                           class="w-full pl-9 pr-4 py-2 text-sm border border-stone-200 rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"/>
                </div>

                {{-- Status --}}
                <select wire:model.live="statusFilter"
                        class="text-sm border border-stone-200 rounded-lg px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-amber-400 text-stone-700">
                    <option value="">All statuses</option>
                    @foreach ($this->statuses as $s)
                        <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
                    @endforeach
                </select>

                {{-- Section --}}
                <select wire:model.live="sectionFilter"
                        class="text-sm border border-stone-200 rounded-lg px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-amber-400 text-stone-700">
                    <option value="">All sections</option>
                    @foreach ($this->sections as $sec)
                        <option value="{{ $sec->value }}">{{ $sec->label() }}</option>
                    @endforeach
                </select>

                {{-- Editor --}}
                <select wire:model.live="editorFilter"
                        class="text-sm border border-stone-200 rounded-lg px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-amber-400 text-stone-700">
                    <option value="">All editors</option>
                    @foreach ($this->editors as $ed)
                        <option value="{{ $ed->id }}">{{ $ed->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Table --}}
            <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="border-b border-stone-100 text-left text-xs text-stone-500 uppercase tracking-wider bg-stone-50">
                        {{-- Select all --}}
                        <th class="pl-4 pr-2 py-3 w-10">
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="all"
                                   @change="
                                       if ($event.target.checked) {
                                           $wire.selected = {{ json_encode($this->posts->pluck('id')->toArray()) }};
                                       } else {
                                           $wire.selected = [];
                                       }
                                   "
                                   class="rounded border-stone-300 text-amber-400 focus:ring-amber-400"/>
                        </th>
                        <th class="px-4 py-3 font-semibold">
                            <button wire:click="sort('title')" class="flex items-center gap-1 hover:text-stone-800">
                                Title @if($sortBy==='title')<span>{{ $sortDir==='asc'?'↑':'↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-semibold">Editor</th>
                        <th class="px-4 py-3 font-semibold">Section</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-center">Views</th>
                        <th class="px-4 py-3 font-semibold">
                            <button wire:click="sort('created_at')" class="flex items-center gap-1 hover:text-stone-800">
                                Date @if($sortBy==='created_at')<span>{{ $sortDir==='asc'?'↑':'↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-50">
                    @forelse ($this->posts as $post)
                        <tr @class([
                        'hover:bg-stone-50 transition-colors group',
                        'opacity-60' => $post->deleted_at,
                    ])>
                            {{-- Checkbox --}}
                            <td class="pl-4 pr-2 py-3">
                                <input type="checkbox"
                                       wire:model.live="selected"
                                       value="{{ $post->id }}"
                                       class="rounded border-stone-300 text-amber-400 focus:ring-amber-400"/>
                            </td>

                            {{-- Title --}}
                            <td class="px-4 py-3.5 max-w-xs">
                                <a wire:navigate href="{{ route('editor.posts.edit', $post) }}"
                                   class="font-semibold text-stone-800 hover:text-amber-600 transition-colors line-clamp-2">
                                    {{ $post->title }}
                                </a>
                                @if ($post->deleted_at)
                                    <span class="text-[10px] text-red-500 font-semibold">Trashed</span>
                                @endif
                            </td>

                            {{-- Editor --}}
                            <td class="px-4 py-3.5 text-stone-500 text-xs">{{ $post->editor->name ?? '—' }}</td>

                            {{-- Section --}}
                            <td class="px-4 py-3.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-amber-700
                                         bg-amber-50 px-2 py-0.5 rounded-full">
                                {{ $post->section->label() }}
                            </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3.5">
                            <span @class([
                                'inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full',
                                'bg-green-100 text-green-700' => $post->status->value === 'published',
                                'bg-stone-100 text-stone-600' => $post->status->value === 'draft',
                                'bg-red-50 text-red-600'     => $post->status->value === 'archived',
                            ])>
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ ucfirst($post->status->value) }}
                            </span>
                            </td>

                            {{-- Views --}}
                            <td class="px-4 py-3.5 text-center text-stone-500">
                                {{ number_format($post->stats?->view_count ?? 0) }}
                            </td>

                            {{-- Date --}}
                            <td class="px-4 py-3.5 text-stone-400 text-xs whitespace-nowrap">
                                {{ $post->created_at->format('d M Y') }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @if (! $post->deleted_at)
                                        <a wire:navigate href="{{ route('editor.posts.edit', $post) }}"
                                           title="Edit"
                                           class="p-1.5 text-stone-400 hover:text-stone-700 rounded-md hover:bg-stone-100">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button wire:click="delete({{ $post->id }})"
                                                wire:confirm="Move '{{ addslashes($post->title) }}' to trash?"
                                                title="Trash"
                                                class="p-1.5 text-red-400 hover:text-red-700 rounded-md hover:bg-red-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @else
                                        <button wire:click="restore({{ $post->id }})"
                                                title="Restore"
                                                class="p-1.5 text-green-400 hover:text-green-700 rounded-md hover:bg-green-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                        <button wire:click="forceDelete({{ $post->id }})"
                                                wire:confirm="Permanently delete '{{ addslashes($post->title) }}'? This cannot be undone."
                                                title="Permanently delete"
                                                class="p-1.5 text-red-500 hover:text-red-800 rounded-md hover:bg-red-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-16 text-center text-stone-400">
                                <p class="font-semibold">No posts match your filters.</p>
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

</x-layouts::app>
