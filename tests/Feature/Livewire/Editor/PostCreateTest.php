<?php

/**
 * tests/Feature/Livewire/Editor/PostCreateTest.php
 *
 * Covers App\Livewire\Editor\PostCreate: access control, validation,
 * and that saving a post also creates its PostStats/PostStatus records
 * (and Picture rows, correctly flagging the featured image).
 */

use App\Enums\PostStatus;
use App\Livewire\Editor\PostCreate;
use App\Models\Post;
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
    it('denies regular users', function () {
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)
            ->test(PostCreate::class)
            ->assertForbidden();
    });

    it('denies guests', function () {
        $this->get(route('editor.posts.create'))->assertRedirect(route('login'));
    });

    it('allows editors', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->assertOk();
    });
});

describe('validation', function () {
    it('requires a title of at least 5 characters', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Hi')
            ->set('content', str_repeat('word ', 20))
            ->call('saveDraft')
            ->assertHasErrors(['title']);
    });

    it('requires content of at least 50 characters', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'A valid title')
            ->set('content', 'Too short')
            ->call('saveDraft')
            ->assertHasErrors(['content']);
    });

    it('rejects a section outside the allowed list', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'A valid title')
            ->set('content', str_repeat('word ', 20))
            ->set('section', 'not-a-real-section')
            ->call('saveDraft')
            ->assertHasErrors(['section']);
    });
});

describe('saveDraft() and publish()', function () {
    it('creates a draft post with a zeroed PostStats row and no active period', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'My First Article')
            ->set('content', str_repeat('word ', 20))
            ->set('section', 'sports')
            ->call('saveDraft')
            ->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'My First Article')->firstOrFail();

        expect($post->editor_id)->toBe($editor->id)
            ->and($post->status)->toBe(PostStatus::Draft)
            ->and($post->slug)->not->toBeEmpty()
            ->and($post->stats->view_count)->toBe(0)
            ->and($post->stats->like_count)->toBe(0)
            ->and($post->stats->comment_count)->toBe(0)
            ->and($post->postStatus->active_period_start)->toBeNull()
            ->and($post->postStatus->is_archived)->toBeFalse();
    });

    it('creates a published post with an active period start', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Breaking News Today')
            ->set('content', str_repeat('word ', 20))
            ->set('section', 'world')
            ->call('publish')
            ->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'Breaking News Today')->firstOrFail();

        expect($post->status)->toBe(PostStatus::Published)
            ->and($post->postStatus->active_period_start)->not->toBeNull();
    });

    it('stores uploaded images as pictures and flags the chosen one as featured', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Article With Pictures')
            ->set('content', str_repeat('word ', 20))
            ->set('uploadedImages', [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.jpg'),
            ])
            ->call('setFeatured', 1)
            ->call('saveDraft')
            ->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'Article With Pictures')->firstOrFail();

        expect($post->pictures)->toHaveCount(2);
        expect($post->pictures->where('is_featured', true))->toHaveCount(1);
        expect($post->pictures->sortBy('id')->values()[1]->is_featured)->toBeTrue();

        // Every stored picture went through ImageOptimizer, not raw ->store().
        $post->pictures->each(
            fn ($picture) => expect($picture->url)->toEndWith('.webp')
        );
    });

    it('rejects more than 10 images', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Too Many Pictures')
            ->set('content', str_repeat('word ', 20))
            ->set('uploadedImages', array_map(
                fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg"),
                range(1, 11)
            ))
            ->call('saveDraft')
            ->assertHasErrors(['uploadedImages']);

        expect(Post::where('title', 'Too Many Pictures')->exists())->toBeFalse();
    });

    it('creates tags from the comma-separated input, reusing existing ones', function () {
        $editor = User::factory()->editor()->create();
        \App\Models\Tag::factory()->create(['name' => 'Elections', 'slug' => 'elections']);

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'A Tagged Article')
            ->set('content', str_repeat('word ', 20))
            ->set('tagsInput', 'Elections,  Climate ,Elections')
            ->call('saveDraft');

        $post = Post::where('title', 'A Tagged Article')->firstOrFail();

        expect(\App\Models\Tag::count())->toBe(2) // Elections reused, Climate created
            ->and($post->tags->pluck('name')->sort()->values()->toArray())->toBe(['Climate', 'Elections']);
    });
});

describe('schedule()', function () {
    it('creates a Scheduled post with active_period_start set to the chosen datetime', function () {
        $editor = User::factory()->editor()->create();
        $scheduledFor = now()->addDays(3)->startOfMinute();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'A Post For Next Week')
            ->set('content', str_repeat('word ', 20))
            ->set('scheduledFor', $scheduledFor->format('Y-m-d\TH:i'))
            ->call('schedule')
            ->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'A Post For Next Week')->firstOrFail();

        expect($post->status)->toBe(PostStatus::Scheduled)
            ->and($post->postStatus->active_period_start->equalTo($scheduledFor))->toBeTrue();
    });

    it('requires a future datetime', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'A Post With No Schedule')
            ->set('content', str_repeat('word ', 20))
            ->set('scheduledFor', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('schedule')
            ->assertHasErrors(['scheduledFor']);

        expect(Post::where('title', 'A Post With No Schedule')->exists())->toBeFalse();
    });

    it('requires a datetime to be provided at all', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'A Post With No Schedule')
            ->set('content', str_repeat('word ', 20))
            ->call('schedule')
            ->assertHasErrors(['scheduledFor']);
    });
});

describe('per-image resize choice', function () {
    it('renders the auto/manual resize buttons for a freshly staged image', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('uploadedImages', [UploadedFile::fake()->image('a.jpg', 800, 600)])
            ->assertSeeHtml('wire:click="applyAutoResize(0)"')
            ->assertSeeHtml('wire:click="openManualResize(0)"');
    });

    it('renders the manual resize form once opened, bound to the real input properties', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('uploadedImages', [UploadedFile::fake()->image('a.jpg', 800, 600)])
            ->call('openManualResize', 0)
            ->assertSeeHtml('wire:model="manualWidthInput"')
            ->assertSeeHtml('wire:model="manualHeightInput"')
            ->assertSeeHtml('wire:click="applyManualResize(0)"');
    });

    it('auto-resizes an image on demand and shows the result on save', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Article With Auto Resize')
            ->set('content', str_repeat('word ', 20))
            ->set('uploadedImages', [UploadedFile::fake()->image('huge.jpg', 4000, 3000)])
            ->call('applyAutoResize', 0);

        $processed = $component->get('processedImages')[0];
        expect($processed['mode'])->toBe('auto')
            ->and($processed['width'])->toBe(2000)
            ->and($processed['height'])->toBe(1500)
            ->and($processed['size'])->toBeGreaterThan(0);

        $component->call('saveDraft')->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'Article With Auto Resize')->firstOrFail();
        expect($post->pictures)->toHaveCount(1);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $post->pictures->first()->url));
    });

    it('manually resizes an image to exact dimensions and shows the result on save', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Article With Manual Resize')
            ->set('content', str_repeat('word ', 20))
            ->set('uploadedImages', [UploadedFile::fake()->image('wide.jpg', 1600, 400)])
            ->call('openManualResize', 0)
            ->assertSet('manualFormIndex', 0)
            ->set('manualWidthInput', 500)
            ->set('manualHeightInput', 300)
            ->call('applyManualResize', 0)
            ->assertHasNoErrors()
            ->assertSet('manualFormIndex', null);

        $processed = $component->get('processedImages')[0];
        expect($processed['mode'])->toBe('manual')
            ->and($processed['width'])->toBe(500)
            ->and($processed['height'])->toBe(300);

        $component->call('saveDraft')->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'Article With Manual Resize')->firstOrFail();
        [$width, $height] = getimagesize(Storage::disk('public')->path(
            str_replace('/storage/', '', $post->pictures->first()->url)
        ));
        expect($width)->toBe(500)->and($height)->toBe(300);
    });

    it('requires both width and height for a manual resize', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('uploadedImages', [UploadedFile::fake()->image('a.jpg', 800, 600)])
            ->call('applyManualResize', 0)
            ->assertHasErrors(['manualWidthInput', 'manualHeightInput']);
    });

    it('falls back to automatic resize at save time for images the editor never processed', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Article With Untouched Image')
            ->set('content', str_repeat('word ', 20))
            ->set('uploadedImages', [UploadedFile::fake()->image('untouched.jpg', 800, 600)])
            ->call('saveDraft')
            ->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'Article With Untouched Image')->firstOrFail();
        expect($post->pictures)->toHaveCount(1)
            ->and($post->pictures->first()->url)->toEndWith('.webp');
    });

    it('discards the stored file when redoing a resize choice', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('uploadedImages', [UploadedFile::fake()->image('a.jpg', 800, 600)])
            ->call('applyAutoResize', 0);

        $firstPath = $component->get('processedImages')[0]['path'];
        Storage::disk('public')->assertExists($firstPath);

        $component->call('redoImageResize', 0);

        Storage::disk('public')->assertMissing($firstPath);
        expect($component->get('processedImages'))->toBe([]);
    });

    it('discards the stored file when removing an already-processed image', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('uploadedImages', [UploadedFile::fake()->image('a.jpg', 800, 600)])
            ->call('applyAutoResize', 0);

        $path = $component->get('processedImages')[0]['path'];

        $component->call('removeImage', 0);

        Storage::disk('public')->assertMissing($path);
        expect($component->get('uploadedImages'))->toBe([]);
    });
});

describe('preview', function () {
    it('is closed by default and opens/closes via openPreview()/closePreview()', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->assertSet('showPreview', false)
            ->call('openPreview')
            ->assertSet('showPreview', true)
            ->assertSee('Mobile')
            ->assertSee('Tablet')
            ->assertSee('Desktop')
            ->call('closePreview')
            ->assertSet('showPreview', false);
    });

    it('defaults to the mobile device width and switches via setPreviewDevice()', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->assertSet('previewDevice', 'mobile')
            ->call('setPreviewDevice', 'tablet')
            ->assertSet('previewDevice', 'tablet')
            ->call('setPreviewDevice', 'not-a-real-device')
            ->assertSet('previewDevice', 'mobile');
    });

    it('renders the current title, content and section inside the preview', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'A Preview Title')
            ->set('content', 'Preview body content goes here.')
            ->set('section', 'sports')
            ->call('openPreview')
            ->assertSee('A Preview Title')
            ->assertSee('Preview body content goes here.')
            ->assertSee('Sports');
    });

    it('orders staged images with the featured one first', function () {
        // Uses already-processed (auto-resized) images rather than raw
        // temporaryUrl() calls: Livewire mints a brand new random signed
        // URL every time temporaryUrl() is invoked, even for the same
        // file, so comparing two independent calls is inherently flaky.
        // A processed image's URL is computed once and stored, so it's
        // stable to compare against.
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('uploadedImages', [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.jpg'),
            ])
            ->call('applyAutoResize', 0)
            ->call('applyAutoResize', 1)
            ->call('setFeatured', 1);

        $instance = $component->instance();
        $expectedFeaturedUrl = $instance->processedImages[1]['url'];

        $urls = $instance->previewImageUrls();
        expect($urls)->toHaveCount(2)
            ->and($urls[0])->toBe($expectedFeaturedUrl);
    });

    it('normalizes staged embed and upload videos for the preview', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->call('addVideoEmbed')
            ->set('uploadedVideo', UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'))
            ->call('addVideoUpload');

        $videos = $component->instance()->previewVideos();
        expect($videos)->toHaveCount(2)
            ->and($videos[0]['type'])->toBe('embed')
            ->and($videos[0]['url'])->toContain('dQw4w9WgXcQ')
            ->and($videos[1]['type'])->toBe('upload');
    });
});

describe('videos', function () {
    it('stages a resolved YouTube embed and persists it on save', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Article With A Video')
            ->set('content', str_repeat('word ', 20))
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->call('addVideoEmbed')
            ->assertHasNoErrors('videoEmbedUrl')
            ->assertSet('videoEmbedUrl', '')
            ->call('saveDraft')
            ->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'Article With A Video')->firstOrFail();

        expect($post->videos)->toHaveCount(1);
        expect($post->videos->first()->type)->toBe('embed');
        expect($post->videos->first()->provider)->toBe('youtube');
        expect($post->videos->first()->url)->toContain('dQw4w9WgXcQ');
    });

    it('rejects a link that is neither YouTube nor Vimeo', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('videoEmbedUrl', 'https://example.com/some-video')
            ->call('addVideoEmbed')
            ->assertHasErrors(['videoEmbedUrl']);
    });

    it('stages and persists a directly uploaded video file', function () {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('title', 'Article With An Uploaded Video')
            ->set('content', str_repeat('word ', 20))
            ->set('uploadedVideo', UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'))
            ->call('addVideoUpload')
            ->assertHasNoErrors('uploadedVideo')
            ->assertSet('uploadedVideo', null)
            ->call('saveDraft')
            ->assertRedirect(route('editor.posts'));

        $post = Post::where('title', 'Article With An Uploaded Video')->firstOrFail();

        expect($post->videos)->toHaveCount(1);
        $video = $post->videos->first();
        expect($video->type)->toBe('upload');
        expect($video->file_size)->toBeGreaterThan(0);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $video->url));
    });

    it('caps staged videos at 3 per post', function () {
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=aaaaaaaaaaa')
            ->call('addVideoEmbed')
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=bbbbbbbbbbb')
            ->call('addVideoEmbed')
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=ccccccccccc')
            ->call('addVideoEmbed');

        expect($component->get('stagedVideos'))->toHaveCount(3);

        $component
            ->set('videoEmbedUrl', 'https://www.youtube.com/watch?v=ddddddddddd')
            ->call('addVideoEmbed')
            ->assertHasErrors(['videoEmbedUrl']);

        expect($component->get('stagedVideos'))->toHaveCount(3);
    });
});

describe('computed helpers', function () {
    it('computes word count and reading time from the content', function () {
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($editor)
            ->test(PostCreate::class)
            ->set('content', str_repeat('word ', 200));

        expect($component->instance()->wordCount())->toBe(200)
            ->and($component->instance()->readingTime())->toBe(1);

        // Regression guard: the word/reading-time counters were previously
        // wired with wire:text="$wordCount", which Alpine evaluates as a JS
        // expression, not a Blade one — it crashed in real browsers with
        // "Public method [$wordCount] not found" the moment the component
        // mounted, despite every server-side Livewire::test() assertion
        // passing (no browser JS runs there). Plain Blade interpolation is
        // the fix; assert both the crash-inducing attribute is gone and the
        // values actually render.
        $component->assertDontSee('wire:text')
            ->assertSee('200')
            ->assertSee('min read');
    });
});
