<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Re-encodes uploaded pictures to WebP and caps their dimensions before
 * they ever reach disk, instead of storing whatever a contributor's phone
 * or camera produced (often several MB and far larger than displayed).
 */
class ImageOptimizer
{
    /**
     * Longest side, in pixels. Smaller originals are left at native size —
     * scaleDown() never upscales.
     */
    private const MAX_DIMENSION = 2000;

    private const WEBP_QUALITY = 82;

    private const MIN_MANUAL_DIMENSION = 50;

    private const MAX_MANUAL_DIMENSION = 4000;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Resize (if needed), re-encode to WebP, and store the result.
     * Returns the stored path, ready for Storage::url().
     */
    public function optimizeAndStore(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        return $this->optimizeAndStoreWithMetadata($file, $directory, $disk)['path'];
    }

    /**
     * Same as optimizeAndStore(), but also reports the resulting
     * dimensions and file size — for UIs that show the editor what the
     * automatic resize actually produced.
     *
     * @return array{path: string, width: int, height: int, size: int}
     */
    public function optimizeAndStoreWithMetadata(UploadedFile $file, string $directory, string $disk = 'public'): array
    {
        $image = $this->manager->read($file->getRealPath());
        $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

        return $this->encodeAndStore($image, $directory, $disk);
    }

    /**
     * Manual resize — crops and fills to an exact target width/height
     * (like CSS object-fit: cover), chosen by the editor rather than the
     * automatic path's "shrink to fit within a box, preserve aspect
     * ratio" behavior.
     *
     * @return array{path: string, width: int, height: int, size: int}
     */
    public function resizeExactAndStore(UploadedFile $file, string $directory, int $width, int $height, string $disk = 'public'): array
    {
        $width = max(self::MIN_MANUAL_DIMENSION, min(self::MAX_MANUAL_DIMENSION, $width));
        $height = max(self::MIN_MANUAL_DIMENSION, min(self::MAX_MANUAL_DIMENSION, $height));

        $image = $this->manager->read($file->getRealPath());
        $image->cover($width, $height);

        return $this->encodeAndStore($image, $directory, $disk);
    }

    /**
     * @return array{path: string, width: int, height: int, size: int}
     */
    private function encodeAndStore(ImageInterface $image, string $directory, string $disk): array
    {
        $encoded = $image->toWebp(quality: self::WEBP_QUALITY);
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.webp';

        Storage::disk($disk)->put($path, (string) $encoded);

        return [
            'path' => $path,
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => strlen((string) $encoded),
        ];
    }
}
