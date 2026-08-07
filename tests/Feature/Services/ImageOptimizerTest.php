<?php

/**
 * tests/Feature/Services/ImageOptimizerTest.php
 *
 * Covers App\Services\ImageOptimizer in isolation from the Livewire
 * components that call it. UploadedFile::fake()->image() produces a real,
 * GD-decodable image (not just zero-filled bytes), so these assertions
 * exercise the actual resize/encode pipeline rather than trusting mocks.
 */

use App\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('stores the file under the given directory with a .webp extension', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $path = (new ImageOptimizer())->optimizeAndStore($file, 'posts/pictures');

    expect($path)->toStartWith('posts/pictures/')
        ->and($path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($path);
});

it('encodes real webp bytes regardless of the original format', function () {
    $file = UploadedFile::fake()->image('photo.png', 200, 200);

    $path = (new ImageOptimizer())->optimizeAndStore($file, 'posts/pictures');

    expect(Storage::disk('public')->mimeType($path))->toBe('image/webp');
});

it('downsizes images larger than the maximum dimension, preserving aspect ratio', function () {
    $file = UploadedFile::fake()->image('huge.jpg', 4000, 3000);

    $path = (new ImageOptimizer())->optimizeAndStore($file, 'posts/pictures');

    [$width, $height] = getimagesize(Storage::disk('public')->path($path));

    expect($width)->toBe(2000)->and($height)->toBe(1500);
});

it('does not upscale images smaller than the maximum dimension', function () {
    $file = UploadedFile::fake()->image('small.jpg', 400, 300);

    $path = (new ImageOptimizer())->optimizeAndStore($file, 'posts/pictures');

    [$width, $height] = getimagesize(Storage::disk('public')->path($path));

    expect($width)->toBe(400)->and($height)->toBe(300);
});

it('gives every stored file a unique name, even for identically named uploads', function () {
    $optimizer = new ImageOptimizer();

    $pathA = $optimizer->optimizeAndStore(UploadedFile::fake()->image('a.jpg', 100, 100), 'posts/pictures');
    $pathB = $optimizer->optimizeAndStore(UploadedFile::fake()->image('a.jpg', 100, 100), 'posts/pictures');

    expect($pathA)->not->toBe($pathB);
    Storage::disk('public')->assertExists($pathA);
    Storage::disk('public')->assertExists($pathB);
});

it('substantially shrinks an oversized photo', function () {
    // A large, highly-compressible synthetic photo — the point isn't the
    // exact byte count, just that optimization meaningfully reduces size
    // rather than ballooning it (as re-encoding a bad format choice could).
    $file = UploadedFile::fake()->image('big.jpg', 3000, 2000);
    $originalSize = filesize($file->getRealPath());

    $path = (new ImageOptimizer())->optimizeAndStore($file, 'posts/pictures');

    $optimizedSize = Storage::disk('public')->size($path);

    expect($optimizedSize)->toBeLessThan($originalSize);
});

describe('optimizeAndStoreWithMetadata()', function () {
    it('reports the resulting dimensions and a positive byte size', function () {
        $file = UploadedFile::fake()->image('huge.jpg', 4000, 3000);

        $result = (new ImageOptimizer())->optimizeAndStoreWithMetadata($file, 'posts/pictures');

        expect($result['width'])->toBe(2000)
            ->and($result['height'])->toBe(1500)
            ->and($result['size'])->toBeGreaterThan(0)
            ->and($result['size'])->toBe(Storage::disk('public')->size($result['path']));
    });
});

describe('resizeExactAndStore()', function () {
    it('crops and fills to the exact requested dimensions regardless of source aspect ratio', function () {
        $file = UploadedFile::fake()->image('wide.jpg', 1600, 400);

        $result = (new ImageOptimizer())->resizeExactAndStore($file, 'posts/pictures', 500, 500);

        expect($result['width'])->toBe(500)
            ->and($result['height'])->toBe(500);

        [$width, $height] = getimagesize(Storage::disk('public')->path($result['path']));
        expect($width)->toBe(500)->and($height)->toBe(500);
    });

    it('clamps requested dimensions to a sane range', function () {
        $file = UploadedFile::fake()->image('tiny.jpg', 100, 100);

        $result = (new ImageOptimizer())->resizeExactAndStore($file, 'posts/pictures', 1, 10000);

        expect($result['width'])->toBe(50) // clamped up to the minimum
            ->and($result['height'])->toBe(4000); // clamped down to the maximum
    });
});
