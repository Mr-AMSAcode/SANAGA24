<?php

namespace App\Livewire\Editor;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tag;
use App\Services\ImageOptimizer;
use App\Support\VideoEmbedUrl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Edit Post — Editor')]
class PostEdit extends Component
{
    use WithFileUploads;

    #[Locked]
    public Post $post;

    // ─────────────────────────────────────────────────
    // Editable fields — populated from the post on mount
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
     * New images being staged for upload (not yet persisted).
     */
    #[Rule(['newImages.*' => 'image|max:5120'])]
    public array $newImages = [];

    /**
     * IDs of existing pictures the editor wants to remove.
     */
    public array $removedPictureIds = [];

    /**
     * ID of the picture to mark as featured (existing or new).
     * Null = leave the current featured image unchanged.
     */
    public ?int $featuredPictureId = null;

    /**
     * Per-image resize results for $newImages, keyed by index — same
     * shape and fallback behavior as PostCreate's.
     */
    public array $processedImages = [];

    /**
     * Index of the new image currently showing the manual resize form.
     */
    public ?int $manualFormIndex = null;

    #[Rule('nullable|integer|min:50|max:4000')]
    public ?int $manualWidthInput = null;

    #[Rule('nullable|integer|min:50|max:4000')]
    public ?int $manualHeightInput = null;

    /**
     * Whether a confirmation dialog is open for deleting the post.
     */
    public bool $confirmingDelete = false;

    /**
     * Datetime-local input value for scheduled publishing.
     * Pre-filled from the post's existing schedule, if any.
     */
    #[Rule('nullable|date|after:now')]
    public ?string $scheduledFor = null;

    /**
     * Comma-separated tag names, pre-filled from the post's current tags.
     */
    #[Rule('nullable|string|max:500')]
    public string $tagsInput = '';

    /**
     * IDs of existing videos the editor wants to remove.
     */
    public array $removedVideoIds = [];

    /**
     * Pending YouTube/Vimeo link, staged into $stagedVideos on confirm.
     */
    #[Rule('nullable|url')]
    public string $videoEmbedUrl = '';

    /**
     * Temporary uploaded video file, staged into $stagedVideos on confirm.
     */
    #[Rule('nullable|file|mimes:mp4,webm,mov,ogg|max:51200')]
    public $uploadedVideo = null;

    /**
     * New videos staged for this edit — same shape as PostCreate's.
     */
    public array $stagedVideos = [];

    /**
     * Whether the "view as a visitor" preview modal is open.
     */
    public bool $showPreview = false;

    /**
     * Which device width the preview is currently simulating.
     */
    public string $previewDevice = 'mobile'; // 'mobile' | 'tablet' | 'desktop'

    public function mount(Post $post): void
    {
        Gate::authorize('update', $post);

        $this->post = $post->loadMissing('pictures', 'postStatus', 'tags', 'videos');

        // Populate form fields from the current post data
        $this->title = $post->title;
        $this->content = $post->content;
        $this->section = $post->section->value;
        $this->tagsInput = $post->tags->pluck('name')->implode(', ');

        $featured = $post->pictures->where('is_featured', true)->first();
        $this->featuredPictureId = $featured?->id;

        if ($post->isScheduled() && $post->postStatus?->active_period_start) {
            $this->scheduledFor = $post->postStatus->active_period_start->format('Y-m-d\TH:i');
        }
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
    // Real-time validation
    // ─────────────────────────────────────────────────

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    // ─────────────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────────────

    public function save(): void
    {
        Gate::authorize('update', $this->post);
        $this->validate();
        $this->assertPictureLimitNotExceeded();

        \DB::transaction(function () {
            // Snapshot the pre-edit state — but only if something editable
            // actually changed, so a no-op save doesn't pollute the history.
            $changed = $this->post->title !== $this->title
                || $this->post->content !== $this->content
                || $this->post->section->value !== $this->section;

            if ($changed) {
                $this->post->revisions()->create([
                    'editor_id' => auth()->id(),
                    'title' => $this->post->title,
                    'content' => $this->post->content,
                    'section' => $this->post->section->value,
                ]);
            }

            // Update core fields
            $this->post->update([
                'title' => $this->title,
                'content' => $this->content,
                'section' => $this->section,
            ]);

            // Remove pictures the editor flagged for removal
            if (! empty($this->removedPictureIds)) {
                $this->post->pictures()
                    ->whereIn('id', $this->removedPictureIds)
                    ->delete();
            }

            // Persist newly uploaded images — resized and re-encoded to
            // WebP rather than stored as-is. Images already run through
            // applyAutoResize()/applyManualResize() reuse that stored
            // file; anything untouched is resized automatically now.
            $optimizer = new ImageOptimizer();
            foreach ($this->newImages as $index => $image) {
                $path = $this->processedImages[$index]['path']
                    ?? $optimizer->optimizeAndStore($image, 'posts/pictures');
                $this->post->pictures()->create([
                    'url' => Storage::url($path),
                    'alt_text' => $this->title,
                    'is_featured' => false,
                    'created_at' => now(),
                ]);
            }

            // Update the featured image
            if ($this->featuredPictureId !== null) {
                $this->post->pictures()->update(['is_featured' => false]);
                $this->post->pictures()
                    ->where('id', $this->featuredPictureId)
                    ->update(['is_featured' => true]);
            }

            // Remove videos the editor flagged for removal
            if (! empty($this->removedVideoIds)) {
                $this->post->videos()
                    ->whereIn('id', $this->removedVideoIds)
                    ->delete();
            }

            // Persist newly staged videos — same handling as PostCreate
            foreach ($this->stagedVideos as $staged) {
                if ($staged['type'] === 'embed') {
                    $this->post->videos()->create([
                        'type' => 'embed',
                        'url' => $staged['url'],
                        'provider' => $staged['provider'],
                        'created_at' => now(),
                    ]);
                } else {
                    $path = $staged['file']->store('posts/videos', 'public');

                    $this->post->videos()->create([
                        'type' => 'upload',
                        'url' => Storage::url($path),
                        'provider' => null,
                        'file_size' => $staged['file']->getSize(),
                        'created_at' => now(),
                    ]);
                }
            }

            // Resync tags to exactly match the input
            $tagIds = collect($this->parseTagNames())
                ->map(fn (string $name) => Tag::findOrCreateByName($name)->id);
            $this->post->tags()->sync($tagIds);
        });

        $this->dispatch('post-saved');
        session()->flash('success', 'Post saved successfully.');
        $this->redirect(route('editor.posts'), navigate: true);
    }

    /**
     * Publish a draft post. Validates the status transition.
     */
    public function publish(): void
    {
        Gate::authorize('publish', $this->post);

        abort_unless(
            $this->post->status->canTransitionTo(PostStatus::Published),
            422,
            'This post cannot be published from its current status.'
        );

        $this->validate();

        \DB::transaction(function () {
            $this->post->update(['status' => PostStatus::Published]);

            // Record the publish time in the lifecycle table
            $this->post->postStatus()->updateOrCreate(
                ['post_id' => $this->post->id],
                ['active_period_start' => now(), 'is_archived' => false]
            );
        });

        session()->flash('success', 'Post published successfully.');
        $this->redirect(route('editor.posts'), navigate: true);
    }

    /**
     * Queue the post to go live automatically at $scheduledFor — a
     * background command flips it to Published once that time arrives
     * (see App\Console\Commands\PublishScheduledPosts).
     */
    public function schedule(): void
    {
        Gate::authorize('publish', $this->post);

        abort_unless(
            $this->post->status->canTransitionTo(PostStatus::Scheduled),
            422,
            'This post cannot be scheduled from its current status.'
        );

        $this->validate();
        $this->validate(['scheduledFor' => 'required|date|after:now']);

        \DB::transaction(function () {
            $this->post->update(['status' => PostStatus::Scheduled]);

            $this->post->postStatus()->updateOrCreate(
                ['post_id' => $this->post->id],
                [
                    'active_period_start' => \Illuminate\Support\Carbon::parse($this->scheduledFor),
                    'is_archived' => false,
                ]
            );
        });

        session()->flash('success', 'Post scheduled successfully.');
        $this->redirect(route('editor.posts'), navigate: true);
    }

    /**
     * Move a published post back to draft.
     */
    public function unpublish(): void
    {
        Gate::authorize('update', $this->post);

        abort_unless(
            $this->post->status->canTransitionTo(PostStatus::Draft),
            422
        );

        $this->post->update(['status' => PostStatus::Draft]);
        session()->flash('success', 'Post moved back to draft.');
        $this->redirect(route('editor.posts'), navigate: true);
    }

    /**
     * Archive the post.
     */
    public function archive(): void
    {
        Gate::authorize('delete', $this->post);

        $this->post->update(['status' => PostStatus::Archived]);
        $this->post->postStatus?->archive();

        session()->flash('success', 'Post archived.');
        $this->redirect(route('editor.posts'), navigate: true);
    }

    /**
     * Soft-delete the post (moves to trash).
     */
    public function delete(): void
    {
        Gate::authorize('delete', $this->post);

        $this->post->delete();
        session()->flash('success', 'Post moved to trash.');
        $this->redirect(route('editor.posts'), navigate: true);
    }

    public function toggleRemovePicture(int $id): void
    {
        if (in_array($id, $this->removedPictureIds)) {
            $this->removedPictureIds = array_diff($this->removedPictureIds, [$id]);
        } else {
            $this->removedPictureIds[] = $id;
        }
    }

    /**
     * Remove a staged (not-yet-saved) new image.
     */
    public function removeImage(int $index): void
    {
        $this->discardProcessedImage($index);

        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);

        unset($this->processedImages[$index]);
        $this->processedImages = array_values($this->processedImages);

        if ($this->manualFormIndex === $index) {
            $this->manualFormIndex = null;
        }
    }

    /**
     * Resize a newly staged image automatically and show the result.
     */
    public function applyAutoResize(int $index): void
    {
        $this->processImage($index, 'auto');
    }

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

    public function applyManualResize(int $index): void
    {
        $this->validate([
            'manualWidthInput' => 'required|integer|min:50|max:4000',
            'manualHeightInput' => 'required|integer|min:50|max:4000',
        ]);

        $this->processImage($index, 'manual', $this->manualWidthInput, $this->manualHeightInput);
        $this->manualFormIndex = null;
    }

    public function redoImageResize(int $index): void
    {
        $this->discardProcessedImage($index);
        unset($this->processedImages[$index]);
    }

    private function processImage(int $index, string $mode, ?int $width = null, ?int $height = null): void
    {
        if (! isset($this->newImages[$index])) {
            return;
        }

        $this->discardProcessedImage($index);

        $optimizer = new ImageOptimizer();
        $result = $mode === 'manual'
            ? $optimizer->resizeExactAndStore($this->newImages[$index], 'posts/pictures', $width, $height)
            : $optimizer->optimizeAndStoreWithMetadata($this->newImages[$index], 'posts/pictures');

        $this->processedImages[$index] = [
            'mode' => $mode,
            'path' => $result['path'],
            'url' => Storage::url($result['path']),
            'width' => $result['width'],
            'height' => $result['height'],
            'size' => $result['size'],
        ];
    }

    private function discardProcessedImage(int $index): void
    {
        if (isset($this->processedImages[$index])) {
            Storage::disk('public')->delete($this->processedImages[$index]['path']);
        }
    }

    public function toggleRemoveVideo(int $id): void
    {
        if (in_array($id, $this->removedVideoIds)) {
            $this->removedVideoIds = array_diff($this->removedVideoIds, [$id]);
        } else {
            $this->removedVideoIds[] = $id;
        }
    }

    /**
     * Resolve and stage a pasted YouTube/Vimeo link.
     */
    public function addVideoEmbed(): void
    {
        $this->validate(['videoEmbedUrl' => 'required|url']);

        if (! $this->canAttachAnotherVideo()) {
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

        if (! $this->canAttachAnotherVideo()) {
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
     * Remove a staged (not-yet-saved) video.
     */
    public function removeVideo(int $index): void
    {
        unset($this->stagedVideos[$index]);
        $this->stagedVideos = array_values($this->stagedVideos);
    }

    /**
     * A post can have at most 3 videos total (surviving existing + staged).
     */
    private function canAttachAnotherVideo(): bool
    {
        $survivingExisting = $this->post->videos()
            ->whereNotIn('id', $this->removedVideoIds)
            ->count();

        return $survivingExisting + count($this->stagedVideos) < 3;
    }

    /**
     * Roll the post's title/content/section back to an older revision.
     * The current state is snapshotted first, so restoring is itself
     * undoable from the same history list.
     */
    public function restore(int $revisionId): void
    {
        Gate::authorize('update', $this->post);

        $revision = $this->post->revisions()->findOrFail($revisionId);

        \DB::transaction(function () use ($revision) {
            $this->post->revisions()->create([
                'editor_id' => auth()->id(),
                'title' => $this->post->title,
                'content' => $this->post->content,
                'section' => $this->post->section->value,
            ]);

            $this->post->update([
                'title' => $revision->title,
                'content' => $revision->content,
                'section' => $revision->section,
            ]);
        });

        $this->title = $revision->title;
        $this->content = $revision->content;
        $this->section = $revision->section;

        session()->flash('success', 'Restored the '.$revision->created_at->diffForHumans().' version.');
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

    /**
     * A post can have at most 10 pictures total. Not a #[Rule] because it
     * spans two sources (surviving existing pictures + new uploads).
     */
    private function assertPictureLimitNotExceeded(): void
    {
        $survivingExisting = $this->post->pictures()
            ->whereNotIn('id', $this->removedPictureIds)
            ->count();

        if ($survivingExisting + count($this->newImages) > 10) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'newImages' => 'A post can have at most 10 pictures in total.',
            ]);
        }
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
     * Surviving existing pictures (already-stored URLs) plus newly staged
     * ones, featured first — mirrors PostCreate's previewImageUrls().
     *
     * @return array<int, string>
     */
    #[Computed]
    public function previewImageUrls(): array
    {
        $existing = $this->existingPictures
            ->sortByDesc(fn ($picture) => $picture->id === $this->featuredPictureId)
            ->pluck('url');

        $new = collect($this->newImages)
            ->values()
            ->map(fn ($image, $index) => $this->processedImages[$index]['url'] ?? $image->temporaryUrl());

        return $existing->merge($new)->all();
    }

    /**
     * @return array<int, array{type: string, url: string, provider: ?string}>
     */
    #[Computed]
    public function previewVideos(): array
    {
        $existing = $this->existingVideos->map(fn ($video) => [
            'type' => $video->type,
            'url' => $video->url,
            'provider' => $video->provider,
        ]);

        $staged = collect($this->stagedVideos)->map(fn (array $video) => [
            'type' => $video['type'],
            'url' => $video['type'] === 'embed' ? $video['url'] : $video['file']->temporaryUrl(),
            'provider' => $video['provider'] ?? null,
        ]);

        return $existing->merge($staged)->all();
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
    // Computed helpers
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

    #[Computed]
    public function revisions()
    {
        return $this->post->revisions()->with('editor:id,name')->limit(20)->get();
    }

    #[Computed]
    public function existingPictures()
    {
        return $this->post->pictures->whereNotIn('id', $this->removedPictureIds);
    }

    #[Computed]
    public function existingVideos()
    {
        return $this->post->videos->whereNotIn('id', $this->removedVideoIds);
    }

    #[Computed]
    public function canPublish(): bool
    {
        return $this->post->status->canTransitionTo(PostStatus::Published);
    }

    #[Computed]
    public function canUnpublish(): bool
    {
        return $this->post->status->canTransitionTo(PostStatus::Draft);
    }

    #[Computed]
    public function canSchedule(): bool
    {
        return $this->post->status->canTransitionTo(PostStatus::Scheduled);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.editor.post-edit');
    }
}
