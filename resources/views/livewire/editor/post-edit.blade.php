<x-layouts::app :title="'Edit: ' . $post->title">

    <div class="bg-stone-50 min-h-screen">
        <div class="max-w-5xl mx-auto px-6 py-10">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <a wire:navigate href="{{ route('editor.posts') }}"
                       class="p-2 text-stone-400 hover:text-stone-700 rounded-lg hover:bg-stone-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-black text-stone-900 tracking-tight">Edit Post</h1>
                        <p class="text-stone-500 text-sm mt-0.5">
                            <span wire:text="$wordCount"></span> words ·
                            <span wire:text="$readingTime"></span> min read ·
                            <span @class([
                            'font-semibold',
                            'text-green-600' => $post->status->value === 'published',
                            'text-stone-500' => $post->status->value === 'draft',
                            'text-red-500'   => $post->status->value === 'archived',
                        ])>{{ ucfirst($post->status->value) }}</span>
                        </p>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center gap-2">

                    {{-- Preview link if published --}}
                    @if ($post->status->value === 'published')
                        <a wire:navigate href="{{ route('posts.show', $post) }}"
                           class="px-4 py-2.5 text-sm font-semibold text-stone-600 bg-white border border-stone-200
                              rounded-lg hover:bg-stone-50 transition-colors shadow-sm flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            View Live
                        </a>
                    @endif

                    {{-- Save draft --}}
                    <button wire:click="saveDraft" wire:loading.attr="disabled"
                            class="px-4 py-2.5 text-sm font-semibold text-stone-700 bg-white border border-stone-200
                               rounded-lg hover:bg-stone-50 transition-colors shadow-sm">
                        <span wire:loading.remove wire:target="saveDraft">Save Draft</span>
                        <span wire:loading wire:target="saveDraft">Saving…</span>
                    </button>

                    {{-- Publish / Unpublish --}}
                    @if ($this->canPublish)
                        <button wire:click="publish" wire:loading.attr="disabled"
                                class="px-5 py-2.5 text-sm font-bold text-stone-900 bg-amber-400
                                   hover:bg-amber-500 rounded-lg transition-colors shadow-sm">
                            <span wire:loading.remove wire:target="publish">Publish Now</span>
                            <span wire:loading wire:target="publish">Publishing…</span>
                        </button>
                    @elseif ($this->canUnpublish)
                        <button wire:click="unpublish" wire:loading.attr="disabled"
                                wire:confirm="Unpublish this post? It will become a draft."
                                class="px-4 py-2.5 text-sm font-bold text-white bg-stone-600
                                   hover:bg-stone-700 rounded-lg transition-colors shadow-sm">
                            Unpublish
                        </button>
                    @endif

                </div>
            </div>

            {{-- Flash --}}
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- ── LEFT: content ── --}}
                <div class="lg:col-span-2 space-y-5">

                    {{-- Title --}}
                    <div>
                        <input wire:model.live="title" type="text"
                               placeholder="Article title…"
                               class="w-full text-2xl font-bold text-stone-900 placeholder-stone-300
                                  border-0 border-b-2 border-stone-200 focus:border-amber-400
                                  bg-transparent focus:outline-none py-3 transition-colors"/>
                        @error('title')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Content --}}
                    <div>
                        <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-2">Content</label>
                        <textarea wire:model.live="content" rows="22"
                                  placeholder="Write your article here…"
                                  class="w-full text-sm text-stone-800 bg-white border border-stone-200 rounded-xl
                                     p-4 focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none font-mono leading-relaxed">
                    </textarea>
                        @error('content')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                {{-- ── RIGHT: meta sidebar ── --}}
                <div class="space-y-5">

                    {{-- Section picker --}}
                    <div class="bg-white rounded-xl border border-stone-200 p-5">
                        <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">Section</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($this->sections as $sec)
                                <button wire:click="$set('section', '{{ $sec->value }}')" type="button"
                                    @class([
                                        'px-3 py-2 rounded-lg text-xs font-semibold text-center transition-colors border',
                                        'bg-amber-400 text-stone-900 border-amber-400' => $section === $sec->value,
                                        'bg-stone-50 text-stone-600 border-stone-200 hover:border-amber-300' => $section !== $sec->value,
                                    ])>
                                    {{ $sec->label() }}
                                </button>
                            @endforeach
                        </div>
                        @error('section')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Existing images --}}
                    @if ($this->existingPictures->isNotEmpty())
                        <div class="bg-white rounded-xl border border-stone-200 p-5">
                            <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                                Current Images
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($this->existingPictures as $pic)
                                    <div @class([
                                'relative rounded-lg overflow-hidden border-2 transition-colors',
                                'border-amber-400' => $pic->is_featured,
                                'border-stone-200' => ! $pic->is_featured,
                            ])>
                                        <img src="{{ $pic->url }}" alt="{{ $pic->alt_text }}"
                                             class="w-full aspect-square object-cover"/>
                                        @if ($pic->is_featured)
                                            <span class="absolute top-1 left-1 bg-amber-400 text-stone-900
                                                 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded">
                                        Featured
                                    </span>
                                        @endif
                                        <button wire:click="removeExistingImage({{ $pic->id }})"
                                                class="absolute top-1 right-1 bg-red-500 text-white w-5 h-5
                                               rounded-full flex items-center justify-center text-xs
                                               hover:bg-red-700 transition-colors">
                                            ×
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- New image upload --}}
                    <div class="bg-white rounded-xl border border-stone-200 p-5">
                        <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                            Add Images <span class="text-stone-300 font-normal">(max 5 MB each)</span>
                        </label>
                        <label class="block cursor-pointer border-2 border-dashed border-stone-200
                                  hover:border-amber-400 rounded-xl p-6 text-center transition-colors">
                            <svg class="mx-auto w-8 h-8 text-stone-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm text-stone-400">Click to upload</span>
                            <input wire:model="uploadedImages" type="file" multiple accept="image/*" class="hidden"/>
                        </label>

                        <div wire:loading wire:target="uploadedImages" class="mt-3">
                            <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-400 animate-pulse rounded-full w-3/4"></div>
                            </div>
                            <p class="text-xs text-stone-400 mt-1">Uploading…</p>
                        </div>

                        @error('uploadedImages.*')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        @if (count($uploadedImages ?? []))
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                @foreach ($uploadedImages as $i => $image)
                                    <div class="relative rounded-lg overflow-hidden border-2 border-stone-200">
                                        <img src="{{ $image->temporaryUrl() }}" alt="Preview"
                                             class="w-full aspect-square object-cover"/>
                                        <button wire:click="removeImage({{ $i }})"
                                                class="absolute top-1 right-1 bg-red-500 text-white w-5 h-5
                                                   rounded-full flex items-center justify-center text-xs
                                                   hover:bg-red-700 transition-colors">×</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Danger zone --}}
                    <div class="bg-white rounded-xl border border-red-100 p-5">
                        <label class="block text-xs font-semibold text-red-400 uppercase tracking-wider mb-3">
                            Danger Zone
                        </label>
                        <div class="space-y-2">
                            @if ($post->status->value !== 'archived')
                                <button wire:click="archive"
                                        wire:confirm="Archive this post? It will be hidden from the public."
                                        class="w-full px-4 py-2 text-xs font-bold text-orange-600 bg-orange-50
                                           hover:bg-orange-100 rounded-lg transition-colors border border-orange-200">
                                    Archive Post
                                </button>
                            @endif
                            <button wire:click="delete"
                                    wire:confirm="Move '{{ addslashes($post->title) }}' to trash? This can be undone."
                                    class="w-full px-4 py-2 text-xs font-bold text-red-600 bg-red-50
                                       hover:bg-red-100 rounded-lg transition-colors border border-red-200">
                                Move to Trash
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</x-layouts::app>
