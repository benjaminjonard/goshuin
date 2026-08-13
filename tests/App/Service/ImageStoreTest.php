<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Service\ImageRefused;
use App\Service\ImageStore;
use App\Service\StoredImage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class ImageStoreTest extends TestCase
{
    private string $root;

    #[\Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/goshuin-'.bin2hex(random_bytes(6));
        mkdir($this->root, 0o775, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
    }

    public function test_it_keeps_the_original_and_writes_every_derivative(): void
    {
        $stored = $this->store()->store($this->jpeg(2000, 1000));

        $this->assertFileExists($this->root.'/'.$stored->path, 'The original was not retained.');

        foreach ($this->derivatives($stored) as $slot => $path) {
            $width = ImageStore::WIDTHS[$slot];
            $this->assertFileExists($this->root.'/'.$path, sprintf('The %s derivative is missing.', $slot));
            $this->assertSame($width, getimagesize($this->root.'/'.$path)[0], sprintf('The %s derivative has the wrong width.', $slot));
        }
    }

    public function test_every_derivative_is_named_on_the_answer(): void
    {
        $stored = $this->store()->store($this->jpeg(2000, 1000));

        $this->assertNotSame($stored->path, $stored->mini, 'The derivatives were not distinguished from the original.');
        $this->assertSame([$stored->mini, $stored->card, $stored->full], array_values($this->derivatives($stored)));
        $this->assertCount(4, array_unique([$stored->path, $stored->mini, $stored->card, $stored->full]), 'Two slots share a path.');
    }

    public function test_the_derivatives_exist_by_the_time_the_call_returns(): void
    {
        $store = $this->store();

        $stored = $store->store($this->jpeg(1500, 1000));

        $this->assertFileExists($this->root.'/'.$stored->full, 'A derivative was left for later.');
    }

    public function test_it_never_enlarges_a_small_image(): void
    {
        $store = $this->store();

        $stored = $store->store($this->jpeg(200, 100));

        $this->assertSame(200, getimagesize($this->root.'/'.$stored->full)[0], 'A small image was blown up.');
        $this->assertSame(96, getimagesize($this->root.'/'.$stored->mini)[0]);
    }

    public function test_it_accepts_png_and_webp(): void
    {
        foreach (['png', 'webp'] as $format) {
            $stored = $this->store()->store($this->image(300, 200, $format));

            $this->assertFileExists($this->root.'/'.$stored->path, sprintf('%s was refused.', $format));
            $this->assertStringEndsWith('.'.$format, $stored->path);
        }
    }

    public function test_it_refuses_anything_else_and_leaves_nothing_behind(): void
    {
        $file = $this->root.'/not-an-image.txt';
        file_put_contents($file, 'plain text');

        try {
            $this->store()->store(new File($file));
            $this->fail('A text file was accepted.');
        } catch (ImageRefused) {
        }

        $this->assertSame(['not-an-image.txt'], $this->stored(), 'A refused upload left a file behind.');
    }

    public function test_metadata_never_reaches_the_stored_file(): void
    {
        $store = $this->store();
        $source = $this->jpegWithMetadata();

        $stored = $store->store($source);

        $this->assertContains('Orientation', $this->exif($source->getPathname()), 'The fixture carried no metadata, so this proves nothing.');

        foreach ([$stored->path, $stored->mini, $stored->card, $stored->full] as $file) {
            $keys = $this->exif($this->root.'/'.$file);

            $this->assertNotContains('Orientation', $keys, sprintf('%s kept the orientation flag.', $file));

            foreach ($keys as $key) {
                $this->assertStringStartsNotWith('GPS', $key, sprintf('%s kept a GPS tag.', $file));
                $this->assertStringNotContainsStringIgnoringCase('serial', $key, sprintf('%s kept a serial number.', $file));
                $this->assertNotContains($key, ['Artist', 'Copyright', 'OwnerName', 'CameraOwnerName'], sprintf('%s kept an owner name.', $file));
            }
        }
    }

    public function test_a_portrait_scan_is_not_stored_sideways(): void
    {
        $store = $this->store();
        $source = $this->rotatedJpeg(600, 300, 6);

        $this->assertSame([600, 300], \array_slice(getimagesize($source->getPathname()), 0, 2), 'The fixture is not the landscape the test needs.');

        $stored = $store->store($source);
        [$width, $height] = getimagesize($this->root.'/'.$stored->path);

        $this->assertSame([300, 600], [$width, $height], 'The orientation flag was discarded without being applied to the pixels.');
        $this->assertSame(96, getimagesize($this->root.'/'.$stored->mini)[0], 'The derivative was cut from the unrotated pixels.');
    }

    public function test_removing_deletes_exactly_the_path_it_is_given(): void
    {
        $store = $this->store();
        $stored = $store->store($this->jpeg(400, 300));

        $store->remove($stored->mini);

        $this->assertFileDoesNotExist($this->root.'/'.$stored->mini, 'The named file survived.');
        $this->assertFileExists($this->root.'/'.$stored->path, 'Removing one derivative took the original with it.');
        $this->assertFileExists($this->root.'/'.$stored->card, 'Removing one derivative took another with it.');
    }

    /**
     * @return array<string, string>
     */
    private function derivatives(StoredImage $stored): array
    {
        return ['mini' => $stored->mini, 'card' => $stored->card, 'full' => $stored->full];
    }

    private function store(): ImageStore
    {
        return new ImageStore($this->root);
    }

    private function jpeg(int $width, int $height): File
    {
        return $this->image($width, $height, 'jpg');
    }

    private function image(int $width, int $height, string $format): File
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 40) {
            imagefilledrectangle($image, $x, 0, $x + 20, $height, imagecolorallocate($image, $x % 255, 120, 200));
        }

        $path = sys_get_temp_dir().'/fixture-'.bin2hex(random_bytes(6)).'.'.$format;

        match ($format) {
            'jpg' => imagejpeg($image, $path, 92),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 92),
        };

        return new File($path);
    }

    private function rotatedJpeg(int $width, int $height, int $orientation): File
    {
        $file = $this->jpeg($width, $height);
        $bytes = file_get_contents($file->getPathname());

        $exif = "Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01".pack('n', $orientation)."\0\0";
        $segment = "\xFF\xE1".pack('n', \strlen($exif) + 2).$exif;
        file_put_contents($file->getPathname(), substr($bytes, 0, 2).$segment.substr($bytes, 2));

        return new File($file->getPathname());
    }

    private function jpegWithMetadata(): File
    {
        $file = $this->jpeg(600, 400);
        $bytes = file_get_contents($file->getPathname());

        $exif = "Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x06\0\0";
        $segment = "\xFF\xE1".pack('n', \strlen($exif) + 2).$exif;
        file_put_contents($file->getPathname(), substr($bytes, 0, 2).$segment.substr($bytes, 2));

        return new File($file->getPathname());
    }

    /**
     * @return list<string>
     */
    private function exif(string $path): array
    {
        $data = @exif_read_data($path);

        return \is_array($data) ? array_keys(array_diff_key($data, array_flip(['FileName', 'FileDateTime', 'FileSize', 'FileType', 'MimeType', 'SectionsFound', 'COMPUTED']))) : [];
    }

    /**
     * @return list<string>
     */
    private function stored(): array
    {
        $found = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $found[] = $file->getFilename();
        }

        sort($found);

        return $found;
    }
}
