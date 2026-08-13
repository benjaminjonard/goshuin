<?php

declare(strict_types=1);

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Upload
{
    public function __construct(
        private readonly string $pathProperty,
        private readonly string $miniProperty,
        private readonly string $cardProperty,
        private readonly string $fullProperty,
        private readonly ?string $deleteProperty = null,
        private readonly ?string $widthProperty = null,
        private readonly ?string $heightProperty = null,
    ) {
    }

    public static function fromReflectionAttribute(\ReflectionAttribute $reflectionAttribute): self
    {
        $arguments = $reflectionAttribute->getArguments();

        return new self(
            $arguments['pathProperty'],
            $arguments['miniProperty'],
            $arguments['cardProperty'],
            $arguments['fullProperty'],
            $arguments['deleteProperty'] ?? null,
            $arguments['widthProperty'] ?? null,
            $arguments['heightProperty'] ?? null,
        );
    }

    public function getPathProperty(): string
    {
        return $this->pathProperty;
    }

    public function getMiniProperty(): string
    {
        return $this->miniProperty;
    }

    public function getCardProperty(): string
    {
        return $this->cardProperty;
    }

    public function getFullProperty(): string
    {
        return $this->fullProperty;
    }

    public function getDeleteProperty(): ?string
    {
        return $this->deleteProperty;
    }

    /**
     * @return list<string>
     */
    public function getSizeProperties(): array
    {
        return array_values(array_filter([$this->widthProperty, $this->heightProperty]));
    }

    /**
     * @return list<string>
     */
    public function getColumnProperties(): array
    {
        return [$this->pathProperty, $this->miniProperty, $this->cardProperty, $this->fullProperty];
    }
}
