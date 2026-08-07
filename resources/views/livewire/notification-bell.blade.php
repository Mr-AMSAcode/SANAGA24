<div class="relative" x-data="{ open: false }">
    <button type="button" x-on:click="open = ! open"
            class="relative p-2 text-white/70 hover:text-white transition-colors" aria-label="{{ __('Notifications') }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute top-0.5 right-0.5 w-4 h-4 flex items-center justify-center bg-red-500
                         text-white text-[9px] font-bold rounded-full">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak x-on:click.outside="open = false"
         class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-700 uppercase tracking-wide">{{ __('Notifications') }}</p>
            @if ($this->unreadCount > 0)
                <button wire:click="markAllAsRead" class="text-[11px] text-blue-600 hover:underline">
                    {{ __('Mark all read') }}
                </button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            @forelse ($this->notifications as $notification)
                <a wire:navigate wire:click="markAsRead('{{ $notification->id }}')"
                   href="{{ route('posts.show', $notification->data['post_slug']) }}"
                   class="block px-4 py-3 hover:bg-gray-50 transition-colors {{ $notification->read_at ? '' : 'bg-blue-50/50' }}">
                    @if ($notification->data['type'] === 'comment_reply')
                        <p class="text-xs text-gray-700">
                            {!! __(':name replied to your comment on :post', [
                                'name' => '<span class="font-semibold">'.e($notification->data['replier_name']).'</span>',
                                'post' => '<span class="font-semibold">'.e($notification->data['post_title']).'</span>',
                            ]) !!}
                        </p>
                        <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $notification->data['excerpt'] }}</p>
                    @elseif ($notification->data['type'] === 'post_published')
                        <p class="text-xs text-gray-700">
                            {!! __('Your scheduled post :post is now live', [
                                'post' => '<span class="font-semibold">'.e($notification->data['post_title']).'</span>',
                            ]) !!}
                        </p>
                    @endif
                    <p class="text-[10px] text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-xs text-gray-400">{{ __('No notifications yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
