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
                    <h1 class="text-2xl font-black text-stone-900 tracking-tight">{{ __('New Post') }}</h1>
                    <p class="text-stone-500 text-sm mt-0.5">
                        <span>{{ $this->wordCount }}</span> {{ __('words') }} ·
                        <span>{{ $this->readingTime }}</span> {{ __('min read') }}
                    </p>
                </div>
            </div>

            {{-- Save actions --}}
            <div class="flex items-center gap-3">
                <button
                    wire:click="openPreview"
                    type="button"
                    class="px-4 py-2.5 text-sm font-semibold text-stone-700 bg-white border border-stone-200
                           rounded-lg hover:bg-stone-50 transition-colors shadow-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    {{ __('Preview') }}
                </button>
                <button
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    class="px-4 py-2.5 text-sm font-semibold text-stone-700 bg-white border border-stone-200
                           rounded-lg hover:bg-stone-50 transition-colors shadow-sm">
                    <span wire:loading.remove wire:target="saveDraft">{{ __('Save Draft') }}</span>
                    <span wire:loading wire:target="saveDraft">{{ __('Saving…') }}</span>
                </button>

                <button
                    wire:click="publish"
                    wire:loading.attr="disabled"
                    class="px-5 py-2.5 text-sm font-bold text-stone-900 bg-amber-400
                           hover:bg-amber-500 rounded-lg transition-colors shadow-sm">
                    <span wire:loading.remove wire:target="publish">{{ __('Publish Now') }}</span>
                    <span wire:loading wire:target="publish">{{ __('Publishing…') }}</span>
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
                        placeholder="{{ __('Article title…') }}"
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
                        {{ __('Content') }}
                    </label>
                    <textarea
                        wire:model.live="content"
                        rows="20"
                        placeholder="{{ __('Start writing…') }}"
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
                        {{ __('Section') }}
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
                                {{ __($sec->label()) }}
                            </button>
                        @endforeach
                    </div>
                    @error('section')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Scheduling --}}
                <div class="bg-white rounded-xl border border-stone-200 p-5">
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                        {{ __('Schedule for later') }} <span class="text-stone-300 font-normal">({{ __('optional') }})</span>
                    </label>
                    <input
                        wire:model="scheduledFor"
                        type="datetime-local"
                        class="w-full text-sm text-stone-700 border border-stone-200 rounded-lg px-3 py-2
                               focus:outline-none focus:ring-2 focus:ring-blue-400"
                    />
                    @error('scheduledFor')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <button
                        wire:click="schedule"
                        wire:loading.attr="disabled"
                        type="button"
                        class="mt-3 w-full px-4 py-2 text-xs font-bold text-white bg-blue-600
                               hover:bg-blue-700 rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="schedule">{{ __('Schedule Post') }}</span>
                        <span wire:loading wire:target="schedule">{{ __('Scheduling…') }}</span>
                    </button>
                </div>

                {{-- Tags --}}
                <div class="bg-white rounded-xl border border-stone-200 p-5">
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                        {{ __('Tags') }} <span class="text-stone-300 font-normal">({{ __('comma-separated') }})</span>
                    </label>
                    <input
                        wire:model="tagsInput"
                        type="text"
                        placeholder="climate, elections, west-africa"
                        class="w-full text-sm text-stone-700 border border-stone-200 rounded-lg px-3 py-2
                               focus:outline-none focus:ring-2 focus:ring-amber-400"
                    />
                    @error('tagsInput')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Image uploader --}}
                <div class="bg-white rounded-xl border border-stone-200 p-5">
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                        {{ __('Images') }} <span class="text-stone-300 font-normal">({{ __('max 10 × 5 MB') }})</span>
                    </label>

                    {{-- Dropzone --}}
                    <label class="block cursor-pointer border-2 border-dashed border-stone-200
                                  hover:border-amber-400 rounded-xl p-6 text-center transition-colors">
                        <svg class="mx-auto w-8 h-8 text-stone-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14
                                     m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-sm text-stone-400">{{ __('Click to upload or drag & drop') }}</span>
                        <input wire:model="uploadedImages" type="file" multiple accept="image/*" class="hidden"/>
                    </label>

                    {{-- Upload progress --}}
                    <div wire:loading wire:target="uploadedImages" class="mt-3">
                        <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-400 animate-pulse rounded-full w-3/4"></div>
                        </div>
                        <p class="text-xs text-stone-400 mt-1">{{ __('Uploading…') }}</p>
                    </div>

                    @error('uploadedImages.*')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Staged images grid --}}
                    @if (count($uploadedImages))
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @foreach ($uploadedImages as $i => $image)
                                <div @class([
                                    'rounded-lg overflow-hidden border-2 transition-colors',
                                    'border-amber-400' => $featuredIndex === $i || ($featuredIndex === null && $i === 0),
                                    'border-stone-200' => ! ($featuredIndex === $i || ($featuredIndex === null && $i === 0)),
                                ])>
                                    <div class="relative">
                                        <img src="{{ $processedImages[$i]['url'] ?? $image->temporaryUrl() }}"
                                             alt="{{ __('Preview :number', ['number' => $i + 1]) }}"
                                             class="w-full h-32 object-cover"/>

                                        {{-- Featured badge --}}
                                        @if ($featuredIndex === $i || ($featuredIndex === null && $i === 0))
                                            <span class="absolute top-1 left-1 bg-amber-400 text-stone-900
                                                         text-[9px] font-bold uppercase px-1.5 py-0.5 rounded">
                                                {{ __('Featured') }}
                                            </span>
                                        @else
                                            <button wire:click="setFeatured({{ $i }})"
                                                    class="absolute top-1 left-1 bg-black/40 text-white
                                                           text-[9px] font-semibold px-1.5 py-0.5 rounded
                                                           hover:bg-amber-400 hover:text-stone-900 transition-colors">
                                                {{ __('Set featured') }}
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

                                    {{-- Resize controls --}}
                                    <div class="p-1.5 bg-stone-50 border-t border-stone-200">
                                        @if (isset($processedImages[$i]))
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="text-[10px] text-stone-500 truncate">
                                                    {{ $processedImages[$i]['width'] }}×{{ $processedImages[$i]['height'] }}
                                                    · {{ number_format($processedImages[$i]['size'] / 1024, 0) }} Ko
                                                    · {{ $processedImages[$i]['mode'] === 'manual' ? __('Manual') : __('Automatic') }}
                                                </span>
                                                <button wire:click="redoImageResize({{ $i }})" type="button"
                                                        class="shrink-0 text-[10px] font-semibold text-amber-700 hover:text-amber-900">
                                                    {{ __('Redo') }}
                                                </button>
                                            </div>
                                        @elseif ($manualFormIndex === $i)
                                            <div class="space-y-1">
                                                <div class="flex gap-1">
                                                    <input wire:model="manualWidthInput" type="number" placeholder="{{ __('Width') }}"
                                                           class="w-1/2 text-[11px] border border-stone-200 rounded px-1.5 py-1
                                                                  [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"/>
                                                    <input wire:model="manualHeightInput" type="number" placeholder="{{ __('Height') }}"
                                                           class="w-1/2 text-[11px] border border-stone-200 rounded px-1.5 py-1
                                                                  [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"/>
                                                </div>
                                                @error('manualWidthInput') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                                                @error('manualHeightInput') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                                                <div class="flex gap-1">
                                                    <button wire:click="applyManualResize({{ $i }})" type="button"
                                                            class="flex-1 text-[10px] font-bold text-stone-900 bg-amber-400 hover:bg-amber-500 rounded py-1">
                                                        {{ __('Apply') }}
                                                    </button>
                                                    <button wire:click="cancelManualResize" type="button"
                                                            class="flex-1 text-[10px] font-semibold text-stone-500 hover:text-stone-700">
                                                        {{ __('Cancel') }}
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex gap-1">
                                                <button wire:click="applyAutoResize({{ $i }})" type="button"
                                                        class="flex-1 text-[10px] font-bold text-stone-900 bg-amber-400 hover:bg-amber-500 rounded py-1">
                                                    {{ __('Automatic') }}
                                                </button>
                                                <button wire:click="openManualResize({{ $i }})" type="button"
                                                        class="flex-1 text-[10px] font-semibold text-stone-600 bg-white border border-stone-200 hover:border-amber-400 rounded py-1">
                                                    {{ __('Manual') }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Video uploader --}}
                <div class="bg-white rounded-xl border border-stone-200 p-5">
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">
                        {{ __('Videos') }} <span class="text-stone-300 font-normal">({{ __('max 3, YouTube/Vimeo or upload') }})</span>
                    </label>

                    {{-- Embed link --}}
                    <div class="flex gap-2">
                        <input
                            wire:model="videoEmbedUrl"
                            wire:keydown.enter.prevent="addVideoEmbed"
                            type="url"
                            placeholder="{{ __('Paste a YouTube or Vimeo link…') }}"
                            class="flex-1 text-sm text-stone-700 border border-stone-200 rounded-lg px-3 py-2
                                   focus:outline-none focus:ring-2 focus:ring-amber-400"
                        />
                        <button
                            wire:click="addVideoEmbed"
                            wire:loading.attr="disabled"
                            type="button"
                            class="px-3 py-2 text-xs font-bold text-stone-900 bg-amber-400
                                   hover:bg-amber-500 rounded-lg transition-colors whitespace-nowrap">
                            {{ __('Add') }}
                        </button>
                    </div>
                    @error('videoEmbedUrl')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Direct upload --}}
                    <div class="mt-3 flex gap-2">
                        <label class="flex-1 cursor-pointer border-2 border-dashed border-stone-200
                                      hover:border-amber-400 rounded-lg px-3 py-2 text-center transition-colors">
                            <span class="text-xs text-stone-400">
                                {{ $uploadedVideo ? $uploadedVideo->getClientOriginalName() : __('Choose a video file…') }}
                            </span>
                            <input wire:model="uploadedVideo" type="file" accept="video/*" class="hidden"/>
                        </label>
                        @if ($uploadedVideo)
                            <button
                                wire:click="addVideoUpload"
                                wire:loading.attr="disabled"
                                type="button"
                                class="px-3 py-2 text-xs font-bold text-stone-900 bg-amber-400
                                       hover:bg-amber-500 rounded-lg transition-colors whitespace-nowrap">
                                {{ __('Add') }}
                            </button>
                        @endif
                    </div>

                    <div wire:loading wire:target="uploadedVideo" class="mt-3">
                        <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-400 animate-pulse rounded-full w-3/4"></div>
                        </div>
                        <p class="text-xs text-stone-400 mt-1">{{ __('Uploading…') }}</p>
                    </div>

                    @error('uploadedVideo')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Staged videos --}}
                    @if (count($stagedVideos))
                        <div class="mt-4 space-y-2">
                            @foreach ($stagedVideos as $i => $video)
                                <div class="relative rounded-lg border border-stone-200 p-2">
                                    <button
                                        wire:click="removeVideo({{ $i }})"
                                        class="absolute top-1 right-1 bg-red-500 text-white
                                               w-5 h-5 rounded-full flex items-center justify-center
                                               text-xs hover:bg-red-700 transition-colors z-10">
                                        ×
                                    </button>

                                    @if ($video['type'] === 'embed')
                                        <div class="flex items-center gap-2 pr-6">
                                            <span class="shrink-0 bg-stone-900 text-white text-[9px] font-bold
                                                         uppercase px-1.5 py-0.5 rounded">
                                                {{ $video['provider'] }}
                                            </span>
                                            <span class="text-xs text-stone-500 truncate">{{ $video['url'] }}</span>
                                        </div>
                                    @else
                                        <video src="{{ $video['file']->temporaryUrl() }}" controls
                                               class="w-full rounded-md max-h-40"></video>
                                        <p class="text-xs text-stone-400 mt-1 truncate pr-6">
                                            {{ $video['file']->getClientOriginalName() }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>{{-- /sidebar --}}
        </div>{{-- /grid --}}
    </div>

    {{-- Preview modal — "view as a visitor on their device" --}}
    @if ($showPreview)
        @php
            $sectionColors = ['politics'=>'bg-[#0d1b4b]','sports'=>'bg-emerald-700','culture'=>'bg-amber-600','science'=>'bg-cyan-700','opinion'=>'bg-rose-700','world'=>'bg-blue-700','actualite'=>'bg-indigo-700'];
            $previewPillClass = ($sectionColors[$section] ?? 'bg-[#0d1b4b]') . ' text-white';
            $previewSection = \App\Enums\PostSection::tryFrom($section);
            $deviceWidths = ['mobile' => '375px', 'tablet' => '768px', 'desktop' => '100%'];
        @endphp
        <div class="fixed inset-0 z-50 bg-black/60 flex flex-col" wire:click.self="closePreview">
            <div class="flex items-center justify-between px-4 py-3 bg-stone-900 text-white flex-shrink-0">
                <span class="text-sm font-bold">{{ __('Preview') }}</span>

                <div class="flex items-center gap-1 bg-white/10 rounded-lg p-1">
                    @foreach (['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'] as $device => $label)
                        <button wire:click="setPreviewDevice('{{ $device }}')" type="button"
                                @class([
                                    'px-3 py-1 text-xs font-semibold rounded transition-colors',
                                    'bg-amber-400 text-stone-900' => $previewDevice === $device,
                                    'text-white/70 hover:text-white' => $previewDevice !== $device,
                                ])>
                            {{ __($label) }}
                        </button>
                    @endforeach
                </div>

                <button wire:click="closePreview" type="button" class="p-1.5 text-white/70 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto flex justify-center py-6 px-4">
                <div class="bg-white shadow-2xl transition-all duration-300 w-full overflow-y-auto"
                     style="max-width: {{ $deviceWidths[$previewDevice] }}">
                    @include('partials._post-preview', [
                        'title' => $title,
                        'content' => $content,
                        'sectionLabel' => __($previewSection?->label() ?? ''),
                        'sectionColorClass' => $previewPillClass,
                        'editorName' => auth()->user()->name,
                        'dateLabel' => now()->isoFormat('LL'),
                        'featuredImageUrl' => $this->previewImageUrls[0] ?? null,
                        'galleryImageUrls' => array_slice($this->previewImageUrls, 1),
                        'videos' => $this->previewVideos,
                        'tagNames' => $this->previewTagNames,
                        'readingTime' => $this->readingTime,
                    ])
                </div>
            </div>
        </div>
    @endif
</div>
