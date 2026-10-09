<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

final class TestImages
{
    public static function jpeg(int $width = 80, int $height = 120, string $name = 'test.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::jpegBinary($width, $height));
    }

    public static function png(int $width = 80, int $height = 120, string $name = 'test.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::pngBinary($width, $height));
    }

    public static function webp(int $width = 40, int $height = 40, bool $animatedMarker = false, string $name = 'test.webp'): UploadedFile
    {
        $binary = self::webpBinary($width, $height);

        if ($animatedMarker) {
            $binary .= 'ANIM';
        }

        return UploadedFile::fake()->createWithContent($name, $binary);
    }

    public static function jpegBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 200, 40, 40);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public static function pngBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 40, 120, 200);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public static function webpBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 40, 200, 120);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagewebp($image, null, 80);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
