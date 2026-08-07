<div class="min-h-screen bg-stone-50">
    <div class="max-w-6xl mx-auto px-6 py-10">

        <div class="mb-8">
            <h1 class="text-2xl font-black text-stone-900 tracking-tight">{{ __('Comment Moderation') }}</h1>
            <p class="text-stone-500 text-sm mt-0.5">{{ __('Review, reject or remove comments across every article') }}</p>
        </div>

        {{-- Flash --}}
        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3 mb-6">
            <div class="relative flex-1 min-w-[200px]">
                <span class="absolute inset-y-0 left-3 flex items-center text-stone-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="search"
                       placeholder="{{ __('Search comment text…') }}"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-stone-200 rounded-lg bg-white
                              focus:outline-none focus:ring-2 focus:ring-amber-400"/>
            </div>

            <select wire:model.live="statusFilter"
                    class="text-sm border border-stone-200 rounded-lg px-3 py-2 bg-white
                       focus:outline-none focus:ring-2 focus:ring-amber-400 text-stone-700">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($this->statuses as $s)
                    <option value="{{ $s->value }}">{{ __($s->label()) }}</option>
                @endforeach
            </select>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-stone-100 text-left text-xs text-stone-500 uppercase tracking-wider bg-stone-50">
                    <th class="px-5 py-3 font-semibold">{{ __('Comment') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Author') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Post') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Date') }}</th>
                    <th class="px-4 py-3 font-semibold text-right">{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-stone-50">
                @forelse ($this->comments as $comment)
                    <tr class="hover:bg-stone-50 transition-colors group">
                        <td class="px-5 py-4 max-w-sm">
                            <p class="text-stone-700 line-clamp-2">{{ $comment->content }}</p>
                        </td>
                        <td class="px-4 py-4 text-stone-500 text-xs">{{ $comment->user->name ?? '—' }}</td>
                        <td class="px-4 py-4 text-xs">
                            @if ($comment->post)
                                <a wire:navigate href="{{ route('posts.show', $comment->post) }}"
                                   class="text-blue-600 hover:underline line-clamp-1">{{ $comment->post->title }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <span @class([
                                'inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full',
                                'bg-green-100 text-green-700' => $comment->status->value === 'approved',
                                'bg-red-50 text-red-600' => $comment->status->value === 'rejected',
                            ])>
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ __($comment->status->label()) }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-stone-400 text-xs whitespace-nowrap">
                            {{ $comment->created_at->isoFormat('ll') }}
                        </td>
                        <td class="px-4 py-4 text-right">
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                @if ($comment->status->value === 'rejected')
                                    <button wire:click="approve({{ $comment->id }})"
                                            title="{{ __('Approve') }}"
                                            class="p-1.5 text-green-500 hover:text-green-700 rounded-md hover:bg-green-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                @else
                                    <button wire:click="reject({{ $comment->id }})"
                                            title="{{ __('Reject (hide from public thread)') }}"
                                            class="p-1.5 text-orange-500 hover:text-orange-700 rounded-md hover:bg-orange-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                    </button>
                                @endif
                                <button wire:click="delete({{ $comment->id }})"
                                        wire:confirm="{{ __('Permanently delete this comment? This cannot be undone.') }}"
                                        title="{{ __('Delete permanently') }}"
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
                            <p class="font-semibold">{{ __('No comments match your filters.') }}</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->comments->hasPages())
            <div class="mt-6">{{ $this->comments->links() }}</div>
        @endif

    </div>
</div>
