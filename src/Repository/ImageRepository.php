<?php

namespace App\Repository;

use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @method Image|null find($id, $lockMode = null, $lockVersion = null)
 * @method Image|null findOneBy(array $criteria, array $orderBy = null)
 * @method Image[]    findAll()
 * @method Image[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageRepository extends ServiceEntityRepository
{
    private CacheInterface $cache;
    public function __construct(
        ManagerRegistry $registry,
        CacheInterface $cache
    ) {
        parent::__construct($registry, Image::class);
        $this->cache = $cache;
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
     * @param int|null $id ID изображения (null для сброса списка)
     * @throws InvalidArgumentException
     */
    public function clearGalleryCache(?int $id = null): void
    {
        // Сброс кэша списка
        $this->cache->delete('gallery_list_query');
        if ($id !== null) {
            $this->cache->delete('gallery_detail_' . $id);
        }
    }
}
