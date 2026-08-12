<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class PhotoInstructions
{
    /**
     * @param list<string>          $order
     * @param array<string, string> $labels
     * @param list<string>          $removed
     * @param list<UploadedFile>    $added
     * @param list<string>          $addedLabels
     */
    public function __construct(
        public array $order = [],
        public array $labels = [],
        public array $removed = [],
        public array $added = [],
        public array $addedLabels = [],
    ) {
    }
}
