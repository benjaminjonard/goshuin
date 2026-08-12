<?php

declare(strict_types=1);

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Upload
{
    public function __construct(
        private readonly string $pathProperty,
        private readonly ?string $deleteProperty = null,
    ) {
    }

    public static function fromReflectionAttribute(\ReflectionAttribute $reflectionAttribute): self
    {
        $arguments = $reflectionAttribute->getArguments();

        return new self($arguments['pathProperty'] ?? $arguments[0], $arguments['deleteProperty'] ?? null);
    }

    public function getPathProperty(): string
    {
        return $this->pathProperty;
    }

    public function getDeleteProperty(): ?string
    {
        return $this->deleteProperty;
    }
}
