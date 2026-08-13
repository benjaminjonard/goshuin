<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

final readonly class ImageStore
{
    public const array WIDTHS = ['mini' => 96, 'card' => 384, 'full' => 1200];

    private const array TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%app.uploads_dir%')] private string $root,
    ) {
    }

    public function store(File $file): StoredImage
    {
        $extension = self::TYPES[(string) $file->getMimeType()] ?? null;

        if ($extension === null) {
            throw new ImageRefused('Only JPEG, PNG and WebP are stored.');
        }

        $name = Uuid::v7()->toRfc4122();
        $directory = substr($name, 0, 2).'/'.substr($name, 2, 2);
        $written = [];

        try {
            $this->makeDirectory($this->root.'/'.$directory);
            $source = $this->read($file, $extension);
            $original = $this->write($source, $directory.'/'.$name.'.'.$extension, $extension, null);
            $written[] = $original;
            $derivatives = [];

            foreach (self::WIDTHS as $slot => $width) {
                $derivatives[$slot] = $this->write($source, $directory.'/'.$name.'-'.$width.'.'.$extension, $extension, $width);
                $written[] = $derivatives[$slot];
            }
        } catch (\Throwable $failure) {
            foreach ($written as $path) {
                @unlink($this->root.'/'.$path);
            }

            throw $failure instanceof ImageRefused ? $failure : new ImageRefused('The image could not be stored.', previous: $failure);
        }

        return new StoredImage(
            $original,
            $derivatives['mini'],
            $derivatives['card'],
            $derivatives['full'],
            imagesx($source),
            imagesy($source),
        );
    }

    public function remove(?string $path): void
    {
        if ($path === null || !str_contains($path, '/')) {
            return;
        }

        @unlink($this->root.'/'.$path);
    }

    private function read(File $file, string $extension): \GdImage
    {
        $orientation = $this->orientation($file, $extension);
        $image = match ($extension) {
            'jpg' => imagecreatefromjpeg($file->getPathname()),
            'png' => imagecreatefrompng($file->getPathname()),
            'webp' => imagecreatefromwebp($file->getPathname()),
        };

        if ($image === false) {
            throw new ImageRefused('The image could not be read.');
        }

        return $this->turn($image, $orientation);
    }

    private function orientation(File $file, string $extension): int
    {
        if ($extension !== 'jpg' || !\function_exists('exif_read_data')) {
            return 1;
        }

        $data = @exif_read_data($file->getPathname());

        return \is_array($data) && isset($data['Orientation']) ? (int) $data['Orientation'] : 1;
    }

    private function turn(\GdImage $image, int $orientation): \GdImage
    {
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $image = imagerotate($image, $angle, 0);
        }

        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, \IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    private function write(\GdImage $source, string $path, string $extension, ?int $width): string
    {
        $target = $width === null ? $source : $this->scale($source, $width);
        $full = $this->root.'/'.$path;

        $written = match ($extension) {
            'jpg' => imagejpeg($target, $full, 82),
            'png' => imagepng($target, $full, 6),
            'webp' => imagewebp($target, $full, 82),
        };

        if ($written === false) {
            throw new ImageRefused('The image could not be written.');
        }

        return $path;
    }

    private function scale(\GdImage $source, int $width): \GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= $width) {
            return $source;
        }

        $height = (int) max(1, round($sourceHeight * $width / $sourceWidth));
        $scaled = imagescale($source, $width, $height);

        if ($scaled === false) {
            throw new ImageRefused('The image could not be resized.');
        }

        return $scaled;
    }

    private function makeDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new ImageRefused('The upload directory could not be created.');
        }
    }
}
