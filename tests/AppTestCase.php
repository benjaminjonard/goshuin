<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class AppTestCase extends WebTestCase
{
    use SignsIn;

    protected KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function createImage(int $width = 900, int $height = 600): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 180, 90, 70));
        $path = sys_get_temp_dir().'/'.bin2hex(random_bytes(5)).'.jpg';
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, 'photograph.jpg', 'image/jpeg', test: true);
    }

    protected function createTextFile(): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.bin2hex(random_bytes(5)).'.txt';
        file_put_contents($path, 'not a photograph');

        return new UploadedFile($path, 'note.txt', 'text/plain', test: true);
    }

    protected function uploadsDir(): string
    {
        return static::getContainer()->getParameter('app.uploads_dir');
    }

    protected function removeUploads(?string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path !== null) {
                @unlink($this->uploadsDir().'/'.$path);
            }
        }
    }

    protected function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();

        return $manager;
    }

    protected function unfiltered(): EntityManagerInterface
    {
        $manager = $this->manager();
        $filters = $manager->getFilters();

        if ($filters->isEnabled('ownership')) {
            $filters->disable('ownership');
        }

        return $manager;
    }
}
