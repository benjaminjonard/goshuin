<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Attribute\Upload;
use App\Attribute\UploadAnnotationReader;
use App\Service\ImageStore;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preFlush)]
#[AsDoctrineListener(event: Events::postRemove)]
final readonly class UploadListener
{
    public function __construct(
        private UploadAnnotationReader $reader,
        private ImageStore $store,
        private PropertyAccessorInterface $accessor,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->take($args->getObject());
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        foreach ($args->getObjectManager()->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                $this->take($entity);
            }
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        foreach ($this->reader->getUploadFields($entity) as $upload) {
            $this->store->remove($this->accessor->getValue($entity, $upload->getPathProperty()));
        }
    }

    private function take(object $entity): void
    {
        foreach ($this->reader->getUploadFields($entity) as $property => $upload) {
            $file = $this->accessor->getValue($entity, $property);

            if (!$file instanceof File) {
                $this->drop($entity, $upload);

                continue;
            }

            $previous = $this->accessor->getValue($entity, $upload->getPathProperty());
            $this->accessor->setValue($entity, $upload->getPathProperty(), $this->store->store($file));
            $this->accessor->setValue($entity, $property, null);
            $this->store->remove($previous);
        }
    }

    private function drop(object $entity, Upload $upload): void
    {
        $flag = $upload->getDeleteProperty();

        if ($flag === null || $this->accessor->getValue($entity, $flag) !== true) {
            return;
        }

        $this->store->remove($this->accessor->getValue($entity, $upload->getPathProperty()));
        $this->accessor->setValue($entity, $upload->getPathProperty(), null);
        $this->accessor->setValue($entity, $flag, false);
    }
}
