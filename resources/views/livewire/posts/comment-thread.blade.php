<div class="mt-12" id="comments">

    <h2 class="text-xl font-black text-stone-900 mb-6 flex items-center gap-2">
        Comments
        <span class="text-sm font-normal text-stone-400 bg-stone-100 px-2.5 py-0.5 rounded-full">
            {{ $this->comments->total() }}
        </span>
    </h2>

    {{-- ══════════════════════════════════════════════
         NEW COMMENT FORM
    ══════════════════════════════════════════════ --}}
    @auth
        <div class="mb-8 bg-white rounded-xl border border-stone-200 p-5 shadow-sm">
            <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-2">
                Leave a comment
            </label>
            <textarea
                wire:model.live="newComment"
                rows="3"
                placeholder="Share your thoughts…"
                class="w-full text-sm text-stone-800 border border-stone-200 rounded-lg p-3
                       focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none"
            ></textarea>
            @error('newComment')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <div class="flex justify-end mt-3">
                <button
                    wire:click="postComment"
                    wire:loading.attr="disabled"
                    class="px-5 py-2 text-sm font-bold text-stone-900 bg-amber-400
                           hover:bg-amber-500 rounded-lg transition-colors">
                    <span wire:loading.remove wire:target="postComment">Post Comment</span>
                    <span wire:loading wire:target="postComment">Posting…</span>
                </button>
            </div>
        </div>
    @else
        <div class="mb-8 bg-stone-50 rounded-xl border border-stone-200 p-5 text-sm text-stone-500 text-center">
            <a wire:navigate href="{{ route('login') }}" class="text-amber-600 font-semibold hover:underline">Sign in</a>
            to join the conversation.
        </div>
    @endauth

    {{-- ══════════════════════════════════════════════
         COMMENT LIST
    ══════════════════════════════════════════════ --}}
    <div class="space-y-6">
        @forelse ($this->comments as $comment)

            {{-- Top-level comment --}}
            <div class="bg-white rounded-xl border border-stone-100 shadow-sm overflow-hidden">

                {{-- Comment body --}}
                <div class="p-5">
                    <div class="flex items-start gap-3">
                        {{-- Avatar --}}
                        <div class="w-9 h-9 rounded-full bg-stone-200 flex items-center justify-center
                                    text-xs font-bold text-stone-600 flex-shrink-0 uppercase">
                            {{ substr($comment->user->name, 0, 1) }}
                        </div>

                        <div class="flex-1 min-w-0">
                            {{-- Author + date --}}
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="font-semibold text-sm text-stone-800">{{ $comment->user->name }}</span>
                                <span class="text-xs text-stone-400">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>

                            {{-- Body --}}
                            <p class="text-sm text-stone-700 leading-relaxed whitespace-pre-wrap">{{ $comment->body }}</p>

                            {{-- Actions row --}}
                            <div class="flex items-center gap-4 mt-3">

                                {{-- Like button for this comment --}}
                                <livewire:posts.like-button :target="$comment" :key="'like-comment-'.$comment->id"/>

                                {{-- Reply --}}
                                @auth
                                    <button wire:click="openReply({{ $comment->id }})"
                                            class="text-xs text-stone-400 hover:text-amber-600 font-medium transition-colors">
                                        Reply
                                    </button>
                                @endauth

                                {{-- Expand replies --}}
                                @if ($comment->replies_count > 0)
                                    <button wire:click="toggleReplies({{ $comment->id }})"
                                            class="text-xs text-stone-400 hover:text-stone-700 font-medium transition-colors">
                                        {{ in_array($comment->id, $expandedReplies) ? 'Hide' : 'Show' }}
                                        {{ $comment->replies_count }}
                                        {{ Str::plural('reply', $comment->replies_count) }}
                                    </button>
                                @endif

                                {{-- Delete (own comment or admin) --}}
                                @if (auth()->id() === $comment->user_id || auth()->user()?->can('comment.delete.any'))
                                    <button wire:click="confirmDelete({{ $comment->id }})"
                                            class="text-xs text-red-400 hover:text-red-600 font-medium transition-colors ml-auto">
                                        Delete
                                    </button>
                                @endif

                            </div>
                        </div>
                    </div>

                    {{-- Inline reply form --}}
                    @if ($replyingToId === $comment->id)
                        <div class="mt-4 pl-12 border-t border-stone-50 pt-4">
                            <textarea
                                wire:model.live="replyBody"
                                rows="2"
                                placeholder="Write a reply…"
                                class="w-full text-sm text-stone-800 border border-stone-200 rounded-lg p-3
                                       focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none"
                            ></textarea>
                            @error('replyBody')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <div class="flex justify-end gap-2 mt-2">
                                <button wire:click="cancelReply"
                                        class="px-3 py-1.5 text-xs font-semibold text-stone-500
                                               hover:text-stone-700 rounded-lg transition-colors">
                                    Cancel
                                </button>
                                <button wire:click="postReply"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-1.5 text-xs font-bold text-stone-900
                                               bg-amber-400 hover:bg-amber-500 rounded-lg transition-colors">
                                    Reply
                                </button>
                            </div>
                        </div>
                    @endif

                </div>

                {{-- Reply thread (expanded) --}}
                @if (in_array($comment->id, $expandedReplies) && $comment->replies->isNotEmpty())
                    <div class="border-t border-stone-50 bg-stone-50/50 px-5 py-4 space-y-4">
                        @foreach ($comment->replies as $reply)
                            <div class="flex items-start gap-3">
                                <div class="w-7 h-7 rounded-full bg-stone-200 flex items-center justify-center
                                            text-[10px] font-bold text-stone-600 flex-shrink-0 uppercase">
                                    {{ substr($reply->user->name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-semibold text-xs text-stone-700">{{ $reply->user->name }}</span>
                                        <span class="text-[11px] text-stone-400">{{ $reply->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm text-stone-600 leading-relaxed">{{ $reply->body }}</p>

                                    {{-- Delete reply --}}
                                    @if (auth()->id() === $reply->user_id || auth()->user()?->can('comment.delete.any'))
                                        <button wire:click="confirmDelete({{ $reply->id }})"
                                                class="text-[11px] text-red-400 hover:text-red-600 mt-1.5 transition-colors">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>{{-- /comment --}}

        @empty
            <div class="text-center py-10 text-stone-400">
                <p class="text-sm">No comments yet. Be the first to share your thoughts!</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if ($this->comments->hasPages())
        <div class="mt-8">{{ $this->comments->links() }}</div>
    @endif

    {{-- ══════════════════════════════════════════════
         DELETE CONFIRMATION MODAL
    ══════════════════════════════════════════════ --}}
    @if ($confirmDeleteId !== null)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full">
                <h3 class="text-lg font-black text-stone-900 mb-2">Delete comment?</h3>
                <p class="text-sm text-stone-500 mb-6">This action cannot be undone.</p>
                <div class="flex gap-3">
                    <button wire:click="cancelDelete"
                            class="flex-1 px-4 py-2.5 text-sm font-semibold text-stone-700
                                   bg-stone-100 hover:bg-stone-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button wire:click="deleteComment"
                            class="flex-1 px-4 py-2.5 text-sm font-bold text-white
                                   bg-red-500 hover:bg-red-600 rounded-lg transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
