<?php

namespace App\Modules\Media\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

class ImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * @return array{
     *     mime_type: string,
     *     extension: string,
     *     original: array{binary: string, width: int, height: int, byte_size: int, checksum: string},
     *     thumb: array{binary: string, width: int, height: int, byte_size: int}|null
     * }
     */
    public function process(UploadedFile $file, string $kind): array
    {
        $this->assertUploadSize($file);

        $binary = file_get_contents($file->getRealPath());

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'file' => ['Unable to read uploaded file.'],
            ]);
        }

        $detectedMime = $this->detectMime($binary);
        $this->assertAllowedMime($detectedMime);
        $this->assertNotAnimated($binary, $detectedMime);

        try {
            $image = $this->manager->read($binary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => ['Image file is corrupt or unreadable.'],
            ]);
        }

        $image->orient();

        $width = $image->width();
        $height = $image->height();
        $this->assertDimensions($width, $height);

        if ($kind === 'cover') {
            $maxEdge = (int) config('media.cover.max_edge_px');
            $this->scaleDownToMaxEdge($image, $maxEdge);
        } else {
            $maxWidth = (int) config('media.chapter_image.max_width_px');
            if ($image->width() > $maxWidth) {
                $image->scale(width: $maxWidth);
            }
        }

        $encoded = $this->encode($image, $detectedMime);
        $originalBinary = (string) $encoded;
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        $thumb = null;

        if ($kind === 'cover') {
            $thumbImage = $this->manager->read($originalBinary);
            $thumbImage->scaleDown(
                width: (int) config('media.cover.thumb_max_width'),
                height: (int) config('media.cover.thumb_max_height'),
            );
            $thumbEncoded = $this->encode($thumbImage, $detectedMime);
            $thumbBinary = (string) $thumbEncoded;
            $thumb = [
                'binary' => $thumbBinary,
                'width' => $thumbImage->width(),
                'height' => $thumbImage->height(),
                'byte_size' => strlen($thumbBinary),
            ];
        }

        return [
            'mime_type' => $detectedMime,
            'extension' => $this->extensionForMime($detectedMime),
            'original' => [
                'binary' => $originalBinary,
                'width' => $originalWidth,
                'height' => $originalHeight,
                'byte_size' => strlen($originalBinary),
                'checksum' => hash('sha256', $originalBinary),
            ],
            'thumb' => $thumb,
        ];
    }

    private function assertUploadSize(UploadedFile $file): void
    {
        $max = (int) config('media.max_upload_bytes');

        if ($file->getSize() > $max) {
            throw ValidationException::withMessages([
                'file' => ['Image exceeds maximum upload size of 10 MiB.'],
            ]);
        }
    }

    private function detectMime(string $binary): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);

        if (! is_string($mime)) {
            throw ValidationException::withMessages([
                'file' => ['Unable to detect image MIME type.'],
            ]);
        }

        return $mime;
    }

    private function assertAllowedMime(string $mime): void
    {
        /** @var list<string> $allowed */
        $allowed = config('media.allowed_mimes');

        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['Only JPEG, PNG and WebP images are allowed.'],
            ]);
        }
    }

    private function assertNotAnimated(string $binary, string $mime): void
    {
        if ($mime === 'image/gif') {
            throw ValidationException::withMessages([
                'file' => ['Animated images are not allowed.'],
            ]);
        }

        if ($mime === 'image/webp' && $this->isAnimatedWebp($binary)) {
            throw ValidationException::withMessages([
                'file' => ['Animated images are not allowed.'],
            ]);
        }

        if ($mime === 'image/png' && str_contains($binary, 'acTL')) {
            throw ValidationException::withMessages([
                'file' => ['Animated images are not allowed.'],
            ]);
        }
    }

    private function isAnimatedWebp(string $binary): bool
    {
        // ANIM chunk indicates animation.
        return str_contains($binary, 'ANIM');
    }

    private function assertDimensions(int $width, int $height): void
    {
        $maxEdge = (int) config('media.max_edge_px');
        $maxMegapixels = (float) config('media.max_megapixels');

        if ($width < 1 || $height < 1) {
            throw ValidationException::withMessages([
                'file' => ['Image dimensions are invalid.'],
            ]);
        }

        if ($width > $maxEdge || $height > $maxEdge) {
            throw ValidationException::withMessages([
                'file' => ['Image edge exceeds maximum allowed size.'],
            ]);
        }

        if (($width * $height) / 1_000_000 > $maxMegapixels) {
            throw ValidationException::withMessages([
                'file' => ['Image exceeds maximum megapixel limit.'],
            ]);
        }
    }

    private function scaleDownToMaxEdge(ImageInterface $image, int $maxEdge): void
    {
        $width = $image->width();
        $height = $image->height();

        if ($width <= $maxEdge && $height <= $maxEdge) {
            return;
        }

        if ($width >= $height) {
            $image->scale(width: $maxEdge);
        } else {
            $image->scale(height: $maxEdge);
        }
    }

    private function encode(ImageInterface $image, string $mime): EncodedImageInterface
    {
        return match ($mime) {
            'image/jpeg' => $image->encode(new JpegEncoder(quality: 85)),
            'image/png' => $image->encode(new PngEncoder),
            'image/webp' => $image->encode(new WebpEncoder(quality: 85)),
            default => throw ValidationException::withMessages([
                'file' => ['Unsupported image format.'],
            ]),
        };
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
