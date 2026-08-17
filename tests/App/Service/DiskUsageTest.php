<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Service\DiskUsage;
use PHPUnit\Framework\TestCase;

class DiskUsageTest extends TestCase
{
    public function test_it_adds_up_every_file_below_the_folder(): void
    {
        $root = sys_get_temp_dir().'/'.bin2hex(random_bytes(5));
        mkdir($root.'/thumbnails', recursive: true);
        file_put_contents($root.'/goshuin.jpg', str_repeat('a', 120));
        file_put_contents($root.'/thumbnails/goshuin.jpg', str_repeat('a', 30));

        $used = new DiskUsage()->of($root);

        unlink($root.'/thumbnails/goshuin.jpg');
        unlink($root.'/goshuin.jpg');
        rmdir($root.'/thumbnails');
        rmdir($root);

        $this->assertSame(150, $used);
    }

    public function test_a_missing_folder_holds_nothing(): void
    {
        $this->assertSame(0, new DiskUsage()->of(sys_get_temp_dir().'/'.bin2hex(random_bytes(5))));
    }
}
