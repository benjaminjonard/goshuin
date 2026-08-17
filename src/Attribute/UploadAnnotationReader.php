<?php

declare(strict_types=1);

namespace App\Attribute;

class UploadAnnotationReader
{
    /**
     * @return array<string, Upload>
     */
    public function getUploadFields(object $entity): array
    {
        $fields = [];

        foreach ((new \ReflectionClass($entity::class))->getProperties() as $property) {
            foreach ($property->getAttributes(Upload::class) as $attribute) {
                $fields[$property->getName()] = $attribute->newInstance();
            }
        }

        return $fields;
    }
}
