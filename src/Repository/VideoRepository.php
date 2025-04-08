<?php

namespace App\Repository;

use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 *
 * @method Video|null find($id, $lockMode = null, $lockVersion = null)
 * @method Video|null findOneBy(array $criteria, array $orderBy = null)
 * @method Video[]    findAll()
 * @method Video[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    /**
     * Сохраняет видео в базе данных
     */
    public function save(Video $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Удаляет видео из базы данных
     */
    public function remove(Video $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Находит видео по ID изображения
     *
     * @return Video[]
     */
    public function findByImageId(int $imageId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.image = :imageId')
            ->setParameter('imageId', $imageId)
            ->getQuery()
            ->enableResultCache(2629800000, 'videos_for_image_' . $imageId)
            ->getResult();
    }

    /**
     * Находит все видео с кэшированием
     *
     * @return Video[]
     */
    public function findAllCached(): array
    {
        return $this->createQueryBuilder('v')
            ->getQuery()
            ->enableResultCache(2629800000, 'all_videos')
            ->getResult();
    }



    /**
     * Очищает кэш видео
     */
    public function clearVideoCache(array $types = []): void
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();
        $keys = [
            'all' => 'all_videos'
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
