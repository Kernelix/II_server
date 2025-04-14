<?php

namespace App\Repository;

use AllowDynamicProperties;
use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @method Image|null find($id, $lockMode = null, $lockVersion = null)
 * @method Image|null findOneBy(array $criteria, array $orderBy = null)
 * @method Image[]    findAll()
 * @method Image[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageRepository extends ServiceEntityRepository
{
    private TagAwareCacheInterface $cache;
    public function __construct(
        ManagerRegistry $registry,
        TagAwareCacheInterface $cache
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
        $this->cache->delete('gallery_list_json');
        $this->cache->delete('gallery_list_etag');
        $this->cache->delete('gallery_all');
        $this->clearImageCache();
        if ($id !== null) {
            $this->cache->delete("gallery_detail_{$id}");
            $this->cache->delete("gallery_detail_{$id}_etag");
            $this->clearImageCache([], $id);
        }
    }
    /**
     * Очищает кэш изображений редис с возможностью фильтрации по типам.
     *
     * @param array $types Очистить только указанные типы ['videos', 'parents', 'featured']
     * @throws InvalidArgumentException
     */
    public function clearImageCache(array $types = [], ?int $id = null): void
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();
        $keys = [
            'gallery' => 'gallery_list_query_db',
            'gallery_v2' => 'gallery_list_query',
            'gallery_v4' => 'gallery_list_query',
        ];

        // Добавляем динамические ключи, если передан id
        if ($id !== null) {
            $keys['gallery_detail'] = 'gallery_detail_' . $id;
            $keys['parent_image'] = "parent_image_" . $id;
            $keys['child_images_'] = "child_images_" . $id;
            $keys['videos_'] = "videos_" . $id;
        }

        $this->cache->invalidateTags(["image_{$id}", "gallery_all"]);


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
