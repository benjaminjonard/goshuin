<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\HttpFoundation\File\UploadedFile;

trait FileTrait
{
    protected function createImage(int $width = 900, int $height = 600, string $format = 'jpg'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 40) {
            imagefilledrectangle($image, $x, 0, $x + 20, $height, imagecolorallocate($image, $x % 255, 120, 200));
        }

        $path = sys_get_temp_dir().'/'.bin2hex(random_bytes(5)).'.'.$format;

        match ($format) {
            'jpg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
        };

        return new UploadedFile($path, 'photograph.'.$format, test: true);
    }

    protected function createTextFile(): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.bin2hex(random_bytes(5)).'.txt';
        file_put_contents($path, 'not a photograph');

        return new UploadedFile($path, 'note.txt', 'text/plain', test: true);
    }
}
