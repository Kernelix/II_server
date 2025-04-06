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
            ->enableResultCache(2629800000, 'images_with_videos') // 1 месяц кэширования
            ->getResult();
    }

    /**
     * @return Image[]|null
     */
    public function findParentImages(): ?array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL')
            ->getQuery()
            ->enableResultCache(2629800000, 'parent_images') // 1 месяц кэширования
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
            ->leftJoin('i.videos', 'v')
            ->leftJoin('i.parentId', 'p')
            ->addSelect('v')
            ->addSelect('p')
            ->getQuery()
            ->enableResultCache(2629800000, 'images_with_videos_and_parent')
            ->getResult();
    }

    /**
     * @return array<Image>
     */
    public function findByFilters(?bool $isPublished = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL');

        if (null !== $isPublished) {
            $qb->andWhere('i.isPublished = :isPublished')
                ->setParameter('isPublished', $isPublished);
        }

        $cacheKey = 'filtered_images_' . ($isPublished ? 'published' : 'unpublished');

        return $qb->getQuery()
            ->enableResultCache(2629800000, $cacheKey)
            ->getResult();
    }

    /**
     * @return array<Image>
     */
    public function findNonFeaturedAndParentImages(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.isFeatured = :isFeatured')
            ->setParameter('isFeatured', false)
            ->andWhere('i.parentId IS NULL')
            ->getQuery()
            ->enableResultCache(2629800000, 'non_featured_parent_images')
            ->getResult();
    }

    /**
     * Очищает кэш изображений с возможностью фильтрации по типам.
     *
     * @param array $types Очистить только указанные типы ['videos', 'parents', 'featured']
     */
    public function clearImageCache(array $types = []): void
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();
        $keys = [
            'videos' => 'images_with_videos',
            'parents' => 'parent_images',
            'full' => 'images_with_videos_and_parent',
            'filtered_published' => 'filtered_images_published',
            'filtered_unpublished' => 'filtered_images_unpublished',
            'non_featured' => 'non_featured_parent_images'
        ];

        if (empty($types)) {
            foreach ($keys as $key) {
                $cache->delete($key);
            }
        } else {
            foreach ($types as $type) {
                if (isset($keys[$type])) {
                    $cache->delete($keys[$type]);
                }
            }
        }
    }
}
