<?php

namespace App\Livewire\Editor;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostStats;
use App\Models\PostStatus as PostStatusModel;
use App\Models\Tag;
use App\Services\ImageOptimizer;
use App\Support\VideoEmbedUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Create New Post — Editor')]
class PostCreate extends Component
{
    use WithFileUploads;

    // ─────────────────────────────────────────────────
    // Form fields with inline validation rules (Livewire 4)
    // ─────────────────────────────────────────────────

    #[Rule('required|string|min:5|max:255')]
    public string $title = '';

    #[Rule('required|string|min:50')]
    public string $content = '';

    // Validated dynamically in rules() below — Rule::in() needs
    // PostSection::values() at runtime, which a #[Rule] attribute (a
    // compile-time constant) can't call.
    public string $section = 'politics';

    /**
     * Temporary uploaded images before the post is saved.
     * Each item: Livewire TemporaryUploadedFile instance.
     * max:5MB, images only, max 10 pictures per post.
     */
    #[Rule([
        'uploadedImages' => 'array|max:10',
        'uploadedImages.*' => 'image|max:5120',
    ])]
    public array $uploadedImages = [];

    /**
     * Index of the featured image in $uploadedImages.
     * Default null = first image is featured once post saves.
     */
    public ?int $featuredIndex = null;

    /**
     * Per-image resize results, keyed by $uploadedImages index. Each
     * entry: ['mode' => 'auto'|'manual', 'path' => ..., 'width' => ...,
     * 'height' => ..., 'size' => ...] — already resized and stored under
     * posts/pictures, ready to attach to the post as-is on save(). Any
     * image without an entry here falls back to automatic resize at
     * save time, so skipping this step entirely still works.
     */
    public array $processedImages = [];

    /**
     * Index of the image currently showing the manual resize form, if any.
     */
    public ?int $manualFormIndex = null;

    #[Rule('nullable|integer|min:50|max:4000')]
    public ?int $manualWidthInput = null;

    #[Rule('nullable|integer|min:50|max:4000')]
    public ?int $manualHeightInput = null;

    /**
     * Datetime-local input value for scheduled publishing.
     * Only required/validated when saveAction === 'schedule'.
     */
    #[Rule('nullable|date|after:now')]
    public ?string $scheduledFor = null;

    /**
     * Comma-separated tag names, e.g. "climate, elections, west-africa".
     */
    #[Rule('nullable|string|max:500')]
    public string $tagsInput = '';

    /**
     * Pending YouTube/Vimeo link, staged into $stagedVideos on confirm.
     */
    #[Rule('nullable|url')]
    public string $videoEmbedUrl = '';

    /**
     * Temporary uploaded video file, staged into $stagedVideos on confirm.
     * Kept separate from $uploadedImages — videos are added one at a time
     * given their size (up to 50MB, matching config/livewire.php's cap).
     */
    #[Rule('nullable|file|mimes:mp4,webm,mov,ogg|max:51200')]
    public $uploadedVideo = null;

    /**
     * Videos staged before the post is saved. Each item:
     * ['type' => 'embed', 'url' => ..., 'provider' => ...] or
     * ['type' => 'upload', 'file' => TemporaryUploadedFile].
     * Capped at 3 per post — kept low since uploads are heavy.
     */
    public array $stagedVideos = [];

    /**
     * Whether to publish immediately, save as draft, or schedule.
     */
    public string $saveAction = 'draft'; // 'draft' | 'publish' | 'schedule'

    /**
     * Whether the "view as a visitor" preview modal is open.
     */
    public bool $showPreview = false;

    /**
     * Which device width the preview is currently simulating.
     */
    public string $previewDevice = 'mobile'; // 'mobile' | 'tablet' | 'desktop'

    // ─────────────────────────────────────────────────
    // Lifecycle
    // ─────────────────────────────────────────────────

    public function mount(): void
    {
        Gate::authorize('create', Post::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'section' => ['required', ValidationRule::in(PostSection::values())],
        ];
    }

    // ─────────────────────────────────────────────────
    // Real-time validation — triggered as the user types
    // ─────────────────────────────────────────────────

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    // ─────────────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────────────

    /**
     * Save as draft — validate, persist, redirect to editor dashboard.
     */
    public function saveDraft(): void
    {
        $this->saveAction = 'draft';
        $this->save();
    }

    /**
     * Publish immediately — validate, persist with Published status.
     */
    public function publish(): void
    {
        $this->saveAction = 'publish';
        $this->save();
    }

    /**
     * Queue the post to go live automatically at $scheduledFor — a
     * background command flips it to Published once that time arrives
     * (see App\Console\Commands\PublishScheduledPosts).
     */
    public function schedule(): void
    {
        $this->saveAction = 'schedule';
        $this->save();
    }

    private function save(): void
    {
        $this->validate();

        if ($this->saveAction === 'schedule') {
            $this->validate(['scheduledFor' => 'required|date|after:now']);
        }

        $status = match ($this->saveAction) {
            'publish' => PostStatus::Published,
            'schedule' => PostStatus::Scheduled,
            default => PostStatus::Draft,
        };

        $activePeriodStart = match ($status) {
            PostStatus::Published => now(),
            PostStatus::Scheduled => \Illuminate\Support\Carbon::parse($this->scheduledFor),
            default => null,
        };

        DB::transaction(function () use ($status, $activePeriodStart) {
            // 1. Create the post
            $post = Post::create([
                'editor_id' => auth()->id(),
                'title' => $this->title,
                'slug' => Post::generateUniqueSlug($this->title),
                'content' => $this->content,
                'section' => $this->section,
                'status' => $status,
            ]);

            // 2. Persist uploaded images — resized and re-encoded to WebP
            // rather than stored as-is (phone photos routinely arrive at
            // several MB and far larger than they're ever displayed). Images
            // the editor already ran through applyAutoResize()/applyManualResize()
            // reuse that stored file; anything left untouched is resized
            // automatically now, so skipping that step entirely still works.
            $optimizer = new ImageOptimizer();
            foreach ($this->uploadedImages as $index => $image) {
                $path = $this->processedImages[$index]['path']
                    ?? $optimizer->optimizeAndStore($image, 'posts/pictures');

                $post->pictures()->create([
                    'url' => Storage::url($path),
                    'alt_text' => $this->title,
                    'is_featured' => $index === ($this->featuredIndex ?? 0),
                    'created_at' => now(),
                ]);
            }

            // 3. Persist staged videos — embeds store the resolved player
            // URL directly; uploads land on the 'public' disk alongside
            // pictures (not resized/re-encoded — that's images-only).
            foreach ($this->stagedVideos as $staged) {
                if ($staged['type'] === 'embed') {
                    $post->videos()->create([
                        'type' => 'embed',
                        'url' => $staged['url'],
                        'provider' => $staged['provider'],
                        'created_at' => now(),
                    ]);
                } else {
                    $path = $staged['file']->store('posts/videos', 'public');

                    $post->videos()->create([
                        'type' => 'upload',
                        'url' => Storage::url($path),
                        'provider' => null,
                        'file_size' => $staged['file']->getSize(),
                        'created_at' => now(),
                    ]);
                }
            }

            // 4. Create the denormalized PostStats record (always 0 on creation)
            PostStats::create([
                'post_id' => $post->id,
                'view_count' => 0,
                'like_count' => 0,
                'comment_count' => 0,
            ]);

            // 5. Create the PostStatus lifecycle record
            PostStatusModel::create([
                'post_id' => $post->id,
                'active_period_start' => $activePeriodStart,
                'active_period_end' => null,
                'is_archived' => false,
            ]);

            // 6. Attach tags (creating any that don't exist yet)
            $tagIds = collect($this->parseTagNames())
                ->map(fn (string $name) => Tag::findOrCreateByName($name)->id);
            $post->tags()->sync($tagIds);

            // 7. Redirect to the editor post list
            $this->redirect(
                route('editor.posts'),
                navigate: true  // Livewire 4 SPA-style navigation
            );
        });
    }

    /**
     * Remove a staged image before saving.
     */
    public function removeImage(int $index): void
    {
        $this->discardProcessedImage($index);

        unset($this->uploadedImages[$index]);
        $this->uploadedImages = array_values($this->uploadedImages);

        unset($this->processedImages[$index]);
        $this->processedImages = array_values($this->processedImages);

        if ($this->featuredIndex === $index) {
            $this->featuredIndex = null;
        }

        if ($this->manualFormIndex === $index) {
            $this->manualFormIndex = null;
        }
    }

    public function setFeatured(int $index): void
    {
        $this->featuredIndex = $index;
    }

    /**
     * Resize an image automatically (shrink to fit within a max box,
     * preserve aspect ratio, convert to WebP) and show the result.
     */
    public function applyAutoResize(int $index): void
    {
        $this->processImage($index, 'auto');
    }

    /**
     * Open the manual resize form (explicit width/height) for an image.
     */
    public function openManualResize(int $index): void
    {
        $this->manualFormIndex = $index;
        $this->manualWidthInput = null;
        $this->manualHeightInput = null;
    }

    public function cancelManualResize(): void
    {
        $this->manualFormIndex = null;
    }

    /**
     * Apply the manual resize form — crops and fills to the exact
     * requested width/height.
     */
    public function applyManualResize(int $index): void
    {
        $this->validate([
            'manualWidthInput' => 'required|integer|min:50|max:4000',
            'manualHeightInput' => 'required|integer|min:50|max:4000',
        ]);

        $this->processImage($index, 'manual', $this->manualWidthInput, $this->manualHeightInput);
        $this->manualFormIndex = null;
    }

    /**
     * Discard a resize result so the editor can choose again.
     */
    public function redoImageResize(int $index): void
    {
        $this->discardProcessedImage($index);
        unset($this->processedImages[$index]);
    }

    private function processImage(int $index, string $mode, ?int $width = null, ?int $height = null): void
    {
        if (! isset($this->uploadedImages[$index])) {
            return;
        }

        $this->discardProcessedImage($index);

        $optimizer = new ImageOptimizer();
        $result = $mode === 'manual'
            ? $optimizer->resizeExactAndStore($this->uploadedImages[$index], 'posts/pictures', $width, $height)
            : $optimizer->optimizeAndStoreWithMetadata($this->uploadedImages[$index], 'posts/pictures');

        $this->processedImages[$index] = [
            'mode' => $mode,
            'path' => $result['path'],
            'url' => Storage::url($result['path']),
            'width' => $result['width'],
            'height' => $result['height'],
            'size' => $result['size'],
        ];
    }

    /**
     * Delete a previously stored resize result for a slot, if any — used
     * before re-processing or removing an image so no orphaned file is
     * left behind under posts/pictures.
     */
    private function discardProcessedImage(int $index): void
    {
        if (isset($this->processedImages[$index])) {
            Storage::disk('public')->delete($this->processedImages[$index]['path']);
        }
    }

    /**
     * Resolve and stage a pasted YouTube/Vimeo link.
     */
    public function addVideoEmbed(): void
    {
        $this->validate(['videoEmbedUrl' => 'required|url']);

        if (count($this->stagedVideos) >= 3) {
            $this->addError('videoEmbedUrl', __('You can attach at most 3 videos per post.'));

            return;
        }

        $resolved = VideoEmbedUrl::resolve($this->videoEmbedUrl);

        if (! $resolved) {
            $this->addError('videoEmbedUrl', __('Only YouTube and Vimeo links are supported.'));

            return;
        }

        $this->stagedVideos[] = [
            'type' => 'embed',
            'url' => $resolved['url'],
            'provider' => $resolved['provider'],
        ];

        $this->videoEmbedUrl = '';
    }

    /**
     * Stage a directly uploaded video file.
     */
    public function addVideoUpload(): void
    {
        $this->validate(['uploadedVideo' => 'required|file|mimes:mp4,webm,mov,ogg|max:51200']);

        if (count($this->stagedVideos) >= 3) {
            $this->addError('uploadedVideo', __('You can attach at most 3 videos per post.'));

            return;
        }

        $this->stagedVideos[] = [
            'type' => 'upload',
            'file' => $this->uploadedVideo,
        ];

        $this->uploadedVideo = null;
    }

    /**
     * Remove a staged video before saving.
     */
    public function removeVideo(int $index): void
    {
        unset($this->stagedVideos[$index]);
        $this->stagedVideos = array_values($this->stagedVideos);
    }

    /**
     * Split the comma-separated tags input into clean, unique names.
     */
    private function parseTagNames(): array
    {
        return collect(explode(',', $this->tagsInput))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────
    // "View as a visitor" preview
    // ─────────────────────────────────────────────────

    public function openPreview(): void
    {
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }

    public function setPreviewDevice(string $device): void
    {
        $this->previewDevice = in_array($device, ['mobile', 'tablet', 'desktop'], true) ? $device : 'mobile';
    }

    /**
     * Staged images in display order (featured first), using the resized
     * result when the editor already ran one, else the raw temp preview.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function previewImageUrls(): array
    {
        return collect($this->uploadedImages)
            ->values()
            ->sortBy(fn ($image, $index) => $index === ($this->featuredIndex ?? 0) ? 0 : 1)
            ->map(fn ($image, $index) => $this->processedImages[$index]['url'] ?? $image->temporaryUrl())
            ->values() // sortBy()/map() preserve original array keys — reindex so [0] means "first in display order", not "originally-index-0"
            ->all();
    }

    /**
     * Staged videos normalized to the shape _post-preview.blade.php expects.
     *
     * @return array<int, array{type: string, url: string, provider: ?string}>
     */
    #[Computed]
    public function previewVideos(): array
    {
        return collect($this->stagedVideos)
            ->map(fn (array $video) => [
                'type' => $video['type'],
                'url' => $video['type'] === 'embed' ? $video['url'] : $video['file']->temporaryUrl(),
                'provider' => $video['provider'] ?? null,
            ])
            ->all();
    }

    /**
     * parseTagNames() is private — this exposes it to the preview modal's
     * Blade view, which can't call private methods on the component.
     */
    #[Computed]
    public function previewTagNames(): array
    {
        return $this->parseTagNames();
    }

    // ─────────────────────────────────────────────────
    // Computed helpers for the view
    // ─────────────────────────────────────────────────

    #[Computed]
    public function sections(): array
    {
        return PostSection::cases();
    }

    #[Computed]
    public function wordCount(): int
    {
        return str_word_count(strip_tags($this->content));
    }

    #[Computed]
    public function readingTime(): int
    {
        return max(1, (int) ceil($this->wordCount / 200)); // avg 200 wpm
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.editor.post-create');
    }
}
