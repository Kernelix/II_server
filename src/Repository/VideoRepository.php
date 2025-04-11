<?php

namespace App\Repository;

use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\InvalidArgumentException;
use RedisException;

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
     * Очищает кэш видео в Redis по ID видео
     * @param int|null $videoId ID видео (null - очищает весь кеш видео)
     * @throws RedisException
     */
    public function clearVideoCache(?int $videoId = null): void
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();

        if (!$cache instanceof \Redis && !$cache instanceof \Predis\Client) {
            throw new \RuntimeException('Cache adapter is not Redis');
        }

        if ($videoId === null) {
            // Очищаем все ключи вида 'videos_*' (используем SCAN для больших БД)
            $iterator = null;
            do {
                $keys = $cache->scan($iterator, 'videos_*', 1000);
                if (!empty($keys)) {
                    $cache->del($keys);
                }
            } while ($iterator > 0);
        } else {
            // Очищаем конкретный ключ видео
            $cache->del(['videos_' . $videoId]);
        }
    }
}
