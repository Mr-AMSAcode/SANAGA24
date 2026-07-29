<?php

namespace App\Livewire\Editor;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostStats;
use App\Models\PostStatus as PostStatusModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    #[Rule('required|in:politics,sports,culture,science,opinion,world')]
    public string $section = 'politics';

    /**
     * Temporary uploaded images before the post is saved.
     * Each item: Livewire TemporaryUploadedFile instance.
     * max:5MB, images only, max 10 pictures per post.
     */
    #[Rule(['uploadedImages.*' => 'image|max:5120'])]
    public array $uploadedImages = [];

    /**
     * Index of the featured image in $uploadedImages.
     * Default null = first image is featured once post saves.
     */
    public ?int $featuredIndex = null;

    /**
     * Whether to publish immediately or save as draft.
     */
    public string $saveAction = 'draft'; // 'draft' | 'publish'

    // ─────────────────────────────────────────────────
    // Lifecycle
    // ─────────────────────────────────────────────────

    public function mount(): void
    {
        Gate::authorize('create', Post::class);
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

    private function save(): void
    {
        $this->validate();

        $status = $this->saveAction === 'publish'
            ? PostStatus::Published
            : PostStatus::Draft;

        DB::transaction(function () use ($status) {
            // 1. Create the post
            $post = Post::create([
                'editor_id' => auth()->id(),
                'title' => $this->title,
                'slug' => Post::generateUniqueSlug($this->title),
                'content' => $this->content,
                'section' => $this->section,
                'status' => $status,
            ]);

            // 2. Persist uploaded images
            foreach ($this->uploadedImages as $index => $image) {
                $path = $image->store('posts/pictures', 'public');

                $post->pictures()->create([
                    'url' => Storage::url($path),
                    'alt_text' => $this->title,
                    'is_featured' => $index === ($this->featuredIndex ?? 0),
                    'created_at' => now(),
                ]);
            }

            // 3. Create the denormalized PostStats record (always 0 on creation)
            PostStats::create([
                'post_id' => $post->id,
                'view_count' => 0,
                'like_count' => 0,
                'comment_count' => 0,
            ]);

            // 4. Create the PostStatus lifecycle record
            PostStatusModel::create([
                'post_id' => $post->id,
                'active_period_start' => $status === PostStatus::Published ? now() : null,
                'active_period_end' => null,
                'is_archived' => false,
            ]);

            // 5. Redirect to the editor post list
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
        unset($this->uploadedImages[$index]);
        $this->uploadedImages = array_values($this->uploadedImages);

        if ($this->featuredIndex === $index) {
            $this->featuredIndex = null;
        }
    }

    public function setFeatured(int $index): void
    {
        $this->featuredIndex = $index;
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
