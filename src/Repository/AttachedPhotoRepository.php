<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttachedPhoto;
use App\Entity\Interface\Photographed;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @template T of AttachedPhoto
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AttachedPhotoRepository extends ServiceEntityRepository
{
    /**
     * @return list<T>
     */
    public function ofSubject(Photographed $subject): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function lastPosition(Photographed $subject): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(MAX(p.position), 0)')
            ->andWhere('p.subject = :subject')
            ->setParameter('subject', $subject)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
