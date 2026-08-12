<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Service\ImageRefused;
use App\Service\ImageStore;
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
        $path = $this->store()->store($this->jpeg(2000, 1000));

        $this->assertFileExists($this->root.'/'.$path, 'The original was not retained.');

        foreach (ImageStore::WIDTHS as $width) {
            $derivative = $this->root.'/'.$this->store()->derivative($path, $width);
            $this->assertFileExists($derivative, sprintf('The %d px derivative is missing.', $width));
            $this->assertSame($width, getimagesize($derivative)[0], sprintf('The %d px derivative has the wrong width.', $width));
        }
    }

    public function test_the_derivatives_exist_by_the_time_the_call_returns(): void
    {
        $store = $this->store();

        $path = $store->store($this->jpeg(1500, 1000));

        $this->assertFileExists($this->root.'/'.$store->derivative($path, 1200), 'A derivative was left for later.');
    }

    public function test_it_never_enlarges_a_small_image(): void
    {
        $store = $this->store();

        $path = $store->store($this->jpeg(200, 100));

        $this->assertSame(200, getimagesize($this->root.'/'.$store->derivative($path, 1200))[0], 'A small image was blown up.');
        $this->assertSame(96, getimagesize($this->root.'/'.$store->derivative($path, 96))[0]);
    }

    public function test_it_accepts_png_and_webp(): void
    {
        foreach (['png', 'webp'] as $format) {
            $path = $this->store()->store($this->image(300, 200, $format));

            $this->assertFileExists($this->root.'/'.$path, sprintf('%s was refused.', $format));
            $this->assertStringEndsWith('.'.$format, $path);
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

        $path = $store->store($source);

        $this->assertContains('Orientation', $this->exif($source->getPathname()), 'The fixture carried no metadata, so this proves nothing.');

        $files = [$path];

        foreach (ImageStore::WIDTHS as $width) {
            $files[] = $store->derivative($path, $width);
        }

        foreach ($files as $file) {
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

        $path = $store->store($source);
        [$width, $height] = getimagesize($this->root.'/'.$path);

        $this->assertSame([300, 600], [$width, $height], 'The orientation flag was discarded without being applied to the pixels.');
        $this->assertSame(96, getimagesize($this->root.'/'.$store->derivative($path, 96))[0], 'The derivative was cut from the unrotated pixels.');
    }

    public function test_removing_takes_the_derivatives_with_it(): void
    {
        $store = $this->store();
        $path = $store->store($this->jpeg(400, 300));

        $store->remove($path);

        $this->assertSame([], $this->stored(), 'Removing left files behind.');
    }

    public function test_a_twelve_megabyte_photograph_is_stored_within_five_seconds(): void
    {
        $file = $this->noisyJpeg(4200, 3200);
        $this->assertGreaterThan(12_000_000, filesize($file->getPathname()), 'The fixture is not the twelve megabytes the budget is about.');

        $started = microtime(true);
        $this->store()->store($file);

        $this->assertLessThan(5.0, microtime(true) - $started, 'Storing took longer than the budget.');
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

    private function noisyJpeg(int $width, int $height): File
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; $x += 2) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }

        $path = sys_get_temp_dir().'/fixture-'.bin2hex(random_bytes(6)).'.jpg';
        imagejpeg($image, $path, 96);

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
