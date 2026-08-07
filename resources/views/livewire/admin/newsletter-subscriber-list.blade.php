<div class="min-h-screen bg-stone-50">
    <div class="max-w-5xl mx-auto px-6 py-10">

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black text-stone-900 tracking-tight">{{ __('Newsletter Subscribers') }}</h1>
                <p class="text-stone-500 text-sm mt-0.5">{{ trans_choice(':count active subscriber|:count active subscribers', $this->activeCount, ['count' => number_format($this->activeCount)]) }}</p>
            </div>
            <a href="{{ route('admin.newsletter.export') }}"
               class="px-4 py-2 text-xs font-bold text-stone-700 bg-white border border-stone-200
                      rounded-lg hover:bg-stone-50 transition-colors">
                {{ __('Export CSV') }}
            </a>
        </div>

        <div class="relative mb-6 max-w-sm">
            <span class="absolute inset-y-0 left-3 flex items-center text-stone-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
            </span>
            <input wire:model.live.debounce.400ms="search" type="search"
                   placeholder="{{ __('Search by email…') }}"
                   class="w-full pl-9 pr-4 py-2 text-sm border border-stone-200 rounded-lg bg-white
                          focus:outline-none focus:ring-2 focus:ring-amber-400"/>
        </div>

        <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-stone-100 text-left text-xs text-stone-500 uppercase tracking-wider bg-stone-50">
                    <th class="px-5 py-3 font-semibold">{{ __('Email') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Account') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Subscribed') }}</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-stone-50">
                @forelse ($this->subscribers as $subscriber)
                    <tr class="hover:bg-stone-50 transition-colors">
                        <td class="px-5 py-3 text-stone-800">{{ $subscriber->email }}</td>
                        <td class="px-4 py-3 text-stone-500 text-xs">{{ $subscriber->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-stone-400 text-xs whitespace-nowrap">
                            {{ $subscriber->subscribed_at->isoFormat('ll') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-16 text-center text-stone-400">
                            {{ __('No subscribers yet.') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->subscribers->hasPages())
            <div class="mt-6">{{ $this->subscribers->links() }}</div>
        @endif

    </div>
</div>
