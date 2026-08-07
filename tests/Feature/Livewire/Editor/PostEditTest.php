<?php

/**
 * tests/Feature/Livewire/Editor/PostEditTest.php
 *
 * Covers App\Livewire\Editor\PostEdit: ownership-scoped access control,
 * field population on mount, saving (incl. picture management), and the
 * publish/unpublish/archive/delete status transitions.
 */

use App\Enums\PostStatus;
use App\Livewire\Editor\PostEdit;
use App\Models\Picture;
use App\Models\Post;
use App\Models\PostStatus as PostStatusModel;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('access control', function () {
    it('allows the owning editor', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->assertOk();
    });

    it('denies an editor who does not own the post', function () {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();
        $post = Post::factory()->for($otherEditor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->assertForbidden();
    });

    it('allows admins to edit any editor\'s post', function () {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($admin)
            ->test(PostEdit::class, ['post' => $post])
            ->assertOk();
    });
});

describe('mount()', function () {
    it('populates the form fields and the currently featured picture from the post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create([
            'title' => 'Original Title',
            'content' => 'Original content here.',
            'section' => 'science',
        ]);
        $featured = Picture::factory()->featured()->for($post)->create();

        $component = Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $post]);

        expect($component->get('title'))->toBe('Original Title')
            ->and($component->get('content'))->toBe('Original content here.')
            ->and($component->get('section'))->toBe('science')
            ->and($component->get('featuredPictureId'))->toBe($featured->id);
    });

    it('pre-fills the tags input from the post\'s current tags', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $post->tags()->attach(\App\Models\Tag::factory()->count(2)->create());

        $component = Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $post]);

        $names = $post->tags->pluck('name')->implode(', ');
        expect($component->get('tagsInput'))->toBe($names);
    });
});

describe('save()', function () {
    it('updates title, content and section', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create(['section' => 'politics']);

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'Updated Title Here')
            ->set('content', str_repeat('word ', 20))
            ->set('section', 'culture')
            ->call('save')
            ->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect($post->title)->toBe('Updated Title Here')
            ->and($post->section->value)->toBe('culture');
    });

    it('removes flagged pictures and adds newly uploaded ones', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $toRemove = Picture::factory()->for($post)->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->call('toggleRemovePicture', $toRemove->id)
            ->set('newImages', [UploadedFile::fake()->image('new.jpg')])
            ->call('save')
            ->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect(Picture::find($toRemove->id))->toBeNull()
            ->and($post->pictures()->count())->toBe(1)
            ->and($post->pictures()->first()->url)->toEndWith('.webp');
    });

    it('reassigns which picture is featured', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $current = Picture::factory()->featured()->for($post)->create();
        $newFeatured = Picture::factory()->for($post)->create(['is_featured' => false]);

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->set('featuredPictureId', $newFeatured->id)
            ->call('save');

        expect($current->fresh()->is_featured)->toBeFalse()
            ->and($newFeatured->fresh()->is_featured)->toBeTrue();
    });

    it('refuses more than 10 pictures in total, counting survivors plus new uploads', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        Picture::factory()->count(9)->for($post)->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->set('newImages', [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ])
            ->call('save')
            ->assertHasErrors(['newImages']);

        expect($post->pictures()->count())->toBe(9);
    });

    it('resyncs tags to exactly match the input, adding and removing as needed', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $keep = \App\Models\Tag::factory()->create(['name' => 'Keep Me']);
        $drop = \App\Models\Tag::factory()->create(['name' => 'Drop Me']);
        $post->tags()->attach([$keep->id, $drop->id]);

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->set('tagsInput', 'Keep Me, Brand New Tag')
            ->call('save');

        $post->load('tags');
        expect($post->tags->pluck('name')->sort()->values()->toArray())
            ->toBe(['Brand New Tag', 'Keep Me']);
    });

    it('snapshots the pre-edit state as a revision when content actually changes', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create([
            'title' => 'Original Title',
            'content' => str_repeat('original ', 20),
        ]);

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'Updated Title')
            ->set('content', str_repeat('updated ', 20))
            ->call('save');

        expect($post->revisions)->toHaveCount(1)
            ->and($post->revisions->first()->title)->toBe('Original Title')
            ->and($post->revisions->first()->editor_id)->toBe($editor->id);
    });

    it('does not create a revision when nothing editable actually changed', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create([
            'title' => 'Same Title Throughout',
            'content' => str_repeat('same ', 20),
        ]);

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'Same Title Throughout')
            ->set('content', str_repeat('same ', 20))
            ->call('save');

        expect($post->revisions)->toHaveCount(0);
    });
});

describe('new-image upload form wiring', function () {
    it('binds the file input to the real newImages property, not a stale name', function () {
        // Regression guard: this input previously read wire:model="uploadedImages",
        // a property that doesn't exist on this component (it's newImages
        // here, uploadedImages only on PostCreate). Livewire::test()->set()
        // calls bypass the view entirely and would never have caught this —
        // only asserting on the rendered HTML does. Selecting a file through
        // the real form silently did nothing until this was fixed.
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->assertSeeHtml('wire:model="newImages"')
            ->assertDontSeeHtml('wire:model="uploadedImages"');
    });
});

describe('per-image resize choice', function () {
    it('renders the auto/manual resize buttons for a freshly staged image', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('newImages', [UploadedFile::fake()->image('a.jpg', 800, 600)])
            ->assertSeeHtml('wire:click="applyAutoResize(0)"')
            ->assertSeeHtml('wire:click="openManualResize(0)"');
    });

    it('auto-resizes a newly staged image on demand and persists it on save', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->set('newImages', [UploadedFile::fake()->image('huge.jpg', 4000, 3000)])
            ->call('applyAutoResize', 0);

        $processed = $component->get('processedImages')[0];
        expect($processed['mode'])->toBe('auto')
            ->and($processed['width'])->toBe(2000)
            ->and($processed['height'])->toBe(1500);

        $component->call('save')->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect($post->pictures()->count())->toBe(1);
    });

    it('manually resizes a newly staged image to exact dimensions', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->set('newImages', [UploadedFile::fake()->image('wide.jpg', 1600, 400)])
            ->call('openManualResize', 0)
            ->set('manualWidthInput', 500)
            ->set('manualHeightInput', 300)
            ->call('applyManualResize', 0)
            ->assertHasNoErrors();

        $processed = $component->get('processedImages')[0];
        expect($processed['mode'])->toBe('manual')
            ->and($processed['width'])->toBe(500)
            ->and($processed['height'])->toBe(300);
    });

    it('removes a staged image via removeImage(), discarding any processed file', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('newImages', [UploadedFile::fake()->image('a.jpg', 800, 600)])
            ->call('applyAutoResize', 0);

        $path = $component->get('processedImages')[0]['path'];

        $component->call('removeImage', 0);

        Storage::disk('public')->assertMissing($path);
        expect($component->get('newImages'))->toBe([]);
    });

    it('falls back to automatic resize at save time for images never processed', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->set('newImages', [UploadedFile::fake()->image('untouched.jpg', 800, 600)])
            ->call('save')
            ->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect($post->pictures()->count())->toBe(1)
            ->and($post->pictures()->first()->url)->toEndWith('.webp');
    });
});

describe('preview', function () {
    it('opens/closes and switches device width', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->assertSet('showPreview', false)
            ->assertSet('previewDevice', 'mobile')
            ->call('openPreview')
            ->assertSet('showPreview', true)
            ->assertSee('Mobile')
            ->assertSee('Tablet')
            ->assertSee('Desktop')
            ->call('setPreviewDevice', 'desktop')
            ->assertSet('previewDevice', 'desktop')
            ->call('closePreview')
            ->assertSet('showPreview', false);
    });

    it('includes existing pictures and newly staged ones, featured first', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $existing = Picture::factory()->for($post)->create(['url' => '/storage/posts/pictures/existing.webp']);

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('featuredPictureId', $existing->id)
            ->set('newImages', [UploadedFile::fake()->image('new.jpg')])
            ->call('applyAutoResize', 0);

        $instance = $component->instance();
        $urls = $instance->previewImageUrls();

        expect($urls)->toHaveCount(2)
            ->and($urls[0])->toBe('/storage/posts/pictures/existing.webp')
            ->and($urls[1])->toBe($instance->processedImages[0]['url']);
    });

    it('includes existing videos and newly staged ones', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        \App\Models\Video::factory()->embed('vimeo')->for($post)->create(['url' => 'https://player.vimeo.com/video/123456789']);

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->call('addVideoEmbed');

        $videos = $component->instance()->previewVideos();

        expect($videos)->toHaveCount(2)
            ->and($videos[0]['provider'])->toBe('vimeo')
            ->and($videos[1]['provider'])->toBe('youtube');
    });
});

describe('videos', function () {
    it('adds a staged embed and an uploaded video on save', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->set('videoEmbedUrl', 'https://vimeo.com/123456789')
            ->call('addVideoEmbed')
            ->assertHasNoErrors('videoEmbedUrl')
            ->set('uploadedVideo', UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'))
            ->call('addVideoUpload')
            ->assertHasNoErrors('uploadedVideo')
            ->call('save')
            ->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect($post->videos)->toHaveCount(2);
        expect($post->videos->pluck('type')->sort()->values()->toArray())->toBe(['embed', 'upload']);
    });

    it('removes videos flagged with toggleRemoveVideo on save', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $video = \App\Models\Video::factory()->embed()->for($post)->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('title', 'A valid title here')
            ->set('content', str_repeat('word ', 20))
            ->call('toggleRemoveVideo', $video->id)
            ->call('save')
            ->assertRedirect(route('editor.posts'));

        expect(\App\Models\Video::find($video->id))->toBeNull();
    });

    it('refuses to stage a 4th video, counting surviving existing plus new', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        \App\Models\Video::factory()->embed()->count(3)->for($post)->create();

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=aaaaaaaaaaa')
            ->call('addVideoEmbed')
            ->assertHasErrors(['videoEmbedUrl']);

        expect($component->get('stagedVideos'))->toHaveCount(0);
    });
});

describe('restore()', function () {
    it('reverts the post to an older revision and snapshots the current state first', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create([
            'title' => 'Current Title',
            'content' => str_repeat('current ', 20),
        ]);
        $oldRevision = \App\Models\PostRevision::factory()->for($post)->create([
            'title' => 'Old Title',
            'content' => str_repeat('old ', 20),
            'section' => $post->section->value,
        ]);

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('restore', $oldRevision->id);

        $post->refresh();
        expect($post->title)->toBe('Old Title')
            ->and($component->get('title'))->toBe('Old Title')
            ->and($post->revisions()->where('title', 'Current Title')->exists())->toBeTrue();
    });

    it('refuses to restore a revision belonging to a different post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $foreignRevision = \App\Models\PostRevision::factory()->create();

        expect(fn () => Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('restore', $foreignRevision->id)
        )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('status transitions', function () {
    it('publishes a draft post and stamps the active period start', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create(); // draft

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('publish')
            ->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Published)
            ->and($post->postStatus->active_period_start)->not->toBeNull();
    });

    it('refuses to publish an already-published post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->published()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('publish')
            ->assertStatus(422);
    });

    it('unpublishes a published post back to draft', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->published()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('unpublish')
            ->assertRedirect(route('editor.posts'));

        expect($post->fresh()->status)->toBe(PostStatus::Draft);
    });

    it('refuses to unpublish a draft post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create(); // draft

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('unpublish')
            ->assertStatus(422);
    });

    it('archives a post and marks its lifecycle record archived', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->published()->for($editor, 'editor')->create();
        PostStatusModel::factory()->for($post)->create(['is_archived' => false]);

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('archive')
            ->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Archived)
            ->and($post->postStatus->is_archived)->toBeTrue();
    });

    it('soft-deletes the post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->call('delete')
            ->assertRedirect(route('editor.posts'));

        expect(Post::find($post->id))->toBeNull()
            ->and(Post::withTrashed()->find($post->id))->not->toBeNull();
    });
});

describe('schedule()', function () {
    it('moves a draft to Scheduled with the chosen go-live time', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();
        $scheduledFor = now()->addDays(2)->startOfMinute();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('scheduledFor', $scheduledFor->format('Y-m-d\TH:i'))
            ->call('schedule')
            ->assertRedirect(route('editor.posts'));

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Scheduled)
            ->and($post->postStatus->active_period_start->equalTo($scheduledFor))->toBeTrue();
    });

    it('can reschedule an already-scheduled post to a new time', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create(['status' => PostStatus::Scheduled])->fresh();
        $post->update(['editor_id' => $editor->id]);
        PostStatusModel::factory()->for($post)->create(['active_period_start' => now()->addDay()]);

        $newTime = now()->addDays(5)->startOfMinute();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('scheduledFor', $newTime->format('Y-m-d\TH:i'))
            ->call('schedule');

        expect($post->postStatus->fresh()->active_period_start->equalTo($newTime))->toBeTrue();
    });

    it('refuses to schedule a post that is already published', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->published()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('scheduledFor', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('schedule')
            ->assertStatus(422);
    });

    it('requires a future datetime', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('scheduledFor', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('schedule')
            ->assertHasErrors(['scheduledFor']);

        expect($post->fresh()->status)->toBe(PostStatus::Draft);
    });
});

describe('computed transition flags', function () {
    it('canPublish is true for a draft and false for a published post', function () {
        $editor = User::factory()->editor()->create();
        $draft = Post::factory()->for($editor, 'editor')->create();
        $published = Post::factory()->published()->for($editor, 'editor')->create();

        expect(Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $draft])->instance()->canPublish())->toBeTrue();
        expect(Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $published])->instance()->canPublish())->toBeFalse();
    });

    it('canUnpublish is true for a published post and false for a draft', function () {
        $editor = User::factory()->editor()->create();
        $draft = Post::factory()->for($editor, 'editor')->create();
        $published = Post::factory()->published()->for($editor, 'editor')->create();

        expect(Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $published])->instance()->canUnpublish())->toBeTrue();
        expect(Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $draft])->instance()->canUnpublish())->toBeFalse();
    });

    it('canSchedule is true for a draft and false for an archived post', function () {
        $editor = User::factory()->editor()->create();
        $draft = Post::factory()->for($editor, 'editor')->create();
        $archived = Post::factory()->archived()->for($editor, 'editor')->create();

        expect(Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $draft])->instance()->canSchedule())->toBeTrue();
        expect(Livewire::actingAs($editor)->test(PostEdit::class, ['post' => $archived])->instance()->canSchedule())->toBeFalse();
    });
});

describe('computed helpers', function () {
    it('computes word count and reading time from the content, and renders them', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->for($editor, 'editor')->create();

        $component = Livewire::actingAs($editor)
            ->test(PostEdit::class, ['post' => $post])
            ->set('content', str_repeat('word ', 400));

        expect($component->instance()->wordCount())->toBe(400)
            ->and($component->instance()->readingTime())->toBe(2);

        // Regression guard: these counters were previously wired with
        // wire:text="$wordCount" (an Alpine/JS-evaluated directive fed a
        // Blade-style PHP variable name) which crashed in real browsers —
        // "Public method [$wordCount] not found" — the instant the
        // component mounted, even though every server-side
        // Livewire::test() assertion passed since no browser JS runs
        // there. The component also had no wordCount()/readingTime()
        // methods at all until this fix.
        $component->assertDontSee('wire:text')
            ->assertSee('400')
            ->assertSee('min read');
    });
});
