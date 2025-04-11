<?php

namespace App\Repository;

use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\InvalidArgumentException;

/**
 * @method Image|null find($id, $lockMode = null, $lockVersion = null)
 * @method Image|null findOneBy(array $criteria, array $orderBy = null)
 * @method Image[]    findAll()
 * @method Image[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function save(Image $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Image $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
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
            ->enableResultCache(2629800000, 'images_with_videos')
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
            ->enableResultCache(2629800000, 'parent_images')
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
     * @throws InvalidArgumentException
     */
    public function clearImageCache(array $types = [], ?int $id = null): void
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();
        $keys = [
            'videos' => 'images_with_videos',
            'parents' => 'parent_images',
            'full' => 'images_with_videos_and_parent',
            'filtered_published' => 'filtered_images_published',
            'filtered_unpublished' => 'filtered_images_unpublished',
            'non_featured' => 'non_featured_parent_images',
            'gallery' => 'gallery_list_query'
        ];

        // Добавляем динамические ключи, если передан id
        if ($id !== null) {
            $keys['child_images'] = 'child_images_' . $id;
            $keys['specific_videos'] = 'videos_' . $id;
        }

        if (empty($types)) {
            $cache->deleteItems($keys); // Удаление нескольких ключей
        } else {
            foreach ($types as $type) {
                if (isset($keys[$type])) {
                    $cache->deleteItem($keys[$type]); // Удаление одного ключа
                }
            }
        }
    }
}
