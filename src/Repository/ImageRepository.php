<?php

namespace App\Repository;

use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    /**
     * Возвращает все изображения с их видео.
     *
     * @return Image[]
     */
    public function findAllWithVideos(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.videos', 'v')
            ->addSelect('v')
            ->getQuery()
            ->getResult();
    }

    public function findParentImages()
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Возвращает все изображения с их видео и родительскими изображениями.
     *
     * @return Image[]
     */
    public function findAllWithVideosAndParent(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.videos', 'v') // Присоединяем связанные видео
            ->leftJoin('i.parentId', 'p') // Присоединяем родительское изображение
            ->addSelect('v') // Выбираем видео
            ->addSelect('p') // Выбираем родительское изображение
            ->getQuery()
            ->getResult();
    }

    public function findByFilters(?bool $isPublished = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL'); // Только родительские изображения

        if ($isPublished !== null) {
            $qb->andWhere('i.isPublished = :isPublished')
                ->setParameter('isPublished', $isPublished);
        }

        return $qb->getQuery()->getResult();
    }
}
