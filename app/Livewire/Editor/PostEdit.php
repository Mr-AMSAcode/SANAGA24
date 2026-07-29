<?php

namespace App\Livewire\Editor;

use App\Enums\PostSection;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    #[Rule('required|in:politics,sports,culture,science,opinion,world')]
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
     * Whether a confirmation dialog is open for deleting the post.
     */
    public bool $confirmingDelete = false;

    public function mount(Post $post): void
    {
        Gate::authorize('update', $post);

        $this->post = $post->loadMissing('pictures');

        // Populate form fields from the current post data
        $this->title = $post->title;
        $this->content = $post->content;
        $this->section = $post->section->value;

        $featured = $post->pictures->where('is_featured', true)->first();
        $this->featuredPictureId = $featured?->id;
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

        \DB::transaction(function () {
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

            // Persist newly uploaded images
            foreach ($this->newImages as $image) {
                $path = $image->store('posts/pictures', 'public');
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

    // ─────────────────────────────────────────────────
    // Computed helpers
    // ─────────────────────────────────────────────────

    #[Computed]
    public function sections(): array
    {
        return PostSection::cases();
    }

    #[Computed]
    public function existingPictures()
    {
        return $this->post->pictures->whereNotIn('id', $this->removedPictureIds);
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

    public function render(): \Illuminate\View\View
    {
        return view('livewire.editor.post-edit');
    }
}
