<div class="min-h-screen bg-stone-50">
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
                    <h1 class="text-2xl font-black text-stone-900 tracking-tight">New Post</h1>
                    <p class="text-stone-500 text-sm mt-0.5">
                        <span wire:text="$wordCount"></span> words ·
                        <span wire:text="$readingTime"></span> min read
                    </p>
                </div>
            </div>

            {{-- Save actions --}}
            <div class="flex items-center gap-3">
                <button
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    class="px-4 py-2.5 text-sm font-semibold text-stone-700 bg-white border border-stone-200
                           rounded-lg hover:bg-stone-50 transition-colors shadow-sm">
                    <span wire:loading.remove wire:target="saveDraft">Save Draft</span>
                    <span wire:loading wire:target="saveDraft">Saving…</span>
                </button>

                <button
                    wire:click="publish"
                    wire:loading.attr="disabled"
                    class="px-5 py-2.5 text-sm font-bold text-stone-900 bg-amber-400
                           hover:bg-amber-500 rounded-lg transition-colors shadow-sm">
                    <span wire:loading.remove wire:target="publish">Publish Now</span>
                    <span wire:loading wire:target="publish">Publishing…</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ── LEFT: Main content area ── --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Title --}}
                <div>
                    <input
                        wire:model.live="title"
                        type="text"
                        placeholder="Article title…"
                        class="w-full text-2xl font-bold text-stone-900 placeholder-stone-300
                               border-0 border-b-2 border-stone-200 focus:border-amber-400
                               bg-transparent focus:outline-none py-3 transition-colors"
                    />
                    @error('title')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Content textarea --}}
                <div>
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-2">
                        Content
                    </label>
                    <textarea
                        wire:model.live="content"
                        rows="20"
                        placeholder="Start writing…"
                        class="w-full text-sm text-stone-800 bg-white border border-stone-200 rounded-xl
                               p-4 focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none
                               font-mono leading-relaxed"
                    ></textarea>
                    @error('content')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- ── RIGHT: Meta sidebar ── --}}
            <div class="space-y-5">

                {{-- Section picker --}}
                <div class="bg-white rounded-xl border border-stone-200 p-5">
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                        Section
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($this->sections as $sec)
                            <button
                                wire:click="$set('section', '{{ $sec->value }}')"
                                type="button"
                                @class([
                                    'px-3 py-2 rounded-lg text-xs font-semibold text-center transition-colors border',
                                    'bg-amber-400 text-stone-900 border-amber-400' => $section === $sec->value,
                                    'bg-stone-50 text-stone-600 border-stone-200 hover:border-amber-300' => $section !== $sec->value,
                                ])
                            >
                                {{ $sec->label() }}
                            </button>
                        @endforeach
                    </div>
                    @error('section')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Image uploader --}}
                <div class="bg-white rounded-xl border border-stone-200 p-5">
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                        Images <span class="text-stone-300 font-normal">(max 10 × 5 MB)</span>
                    </label>

                    {{-- Dropzone --}}
                    <label class="block cursor-pointer border-2 border-dashed border-stone-200
                                  hover:border-amber-400 rounded-xl p-6 text-center transition-colors">
                        <svg class="mx-auto w-8 h-8 text-stone-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14
                                     m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-sm text-stone-400">Click to upload or drag & drop</span>
                        <input wire:model="uploadedImages" type="file" multiple accept="image/*" class="hidden"/>
                    </label>

                    {{-- Upload progress --}}
                    <div wire:loading wire:target="uploadedImages" class="mt-3">
                        <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-400 animate-pulse rounded-full w-3/4"></div>
                        </div>
                        <p class="text-xs text-stone-400 mt-1">Uploading…</p>
                    </div>

                    @error('uploadedImages.*')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Staged images grid --}}
                    @if (count($uploadedImages))
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @foreach ($uploadedImages as $i => $image)
                                <div @class([
                                    'relative rounded-lg overflow-hidden border-2 transition-colors',
                                    'border-amber-400' => $featuredIndex === $i || ($featuredIndex === null && $i === 0),
                                    'border-stone-200' => ! ($featuredIndex === $i || ($featuredIndex === null && $i === 0)),
                                ])>
                                    <img src="{{ $image->temporaryUrl() }}"
                                         alt="Preview {{ $i + 1 }}"
                                         class="w-full aspect-square object-cover"/>

                                    {{-- Featured badge --}}
                                    @if ($featuredIndex === $i || ($featuredIndex === null && $i === 0))
                                        <span class="absolute top-1 left-1 bg-amber-400 text-stone-900
                                                     text-[9px] font-bold uppercase px-1.5 py-0.5 rounded">
                                            Featured
                                        </span>
                                    @else
                                        <button wire:click="setFeatured({{ $i }})"
                                                class="absolute top-1 left-1 bg-black/40 text-white
                                                       text-[9px] font-semibold px-1.5 py-0.5 rounded
                                                       hover:bg-amber-400 hover:text-stone-900 transition-colors">
                                            Set featured
                                        </button>
                                    @endif

                                    {{-- Remove --}}
                                    <button
                                        wire:click="removeImage({{ $i }})"
                                        class="absolute top-1 right-1 bg-red-500 text-white
                                               w-5 h-5 rounded-full flex items-center justify-center
                                               text-xs hover:bg-red-700 transition-colors">
                                        ×
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>{{-- /sidebar --}}
        </div>{{-- /grid --}}
    </div>
</div>
