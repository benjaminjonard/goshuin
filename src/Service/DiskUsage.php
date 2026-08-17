<?php

declare(strict_types=1);

namespace App\Service;

final readonly class DiskUsage
{
    public function of(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $used = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            $used += $file->isFile() ? $file->getSize() : 0;
        }

        return $used;
    }
}
