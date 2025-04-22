<?php

namespace App\Repository;

use App\Entity\Image;
use App\Repository\Interface\ImageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method Image|null find($id, $lockMode = null, $lockVersion = null)
 * @method Image|null findOneBy(array $criteria, array $orderBy = null)
 * @method Image[]    findAll()
 * @method Image[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageRepository extends ServiceEntityRepository implements ImageRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, Image::class);
    }

    public function save($entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove($entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }


    /** @return Image[] */
    public function findParentImages(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL')
            ->getQuery()
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
            ->getResult();
    }

    public function getById(int $id): Image
    {
        $image = $this->createQueryBuilder('i')
            ->andWhere('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$image) {
            throw new NotFoundHttpException("Image {$id} not found");
        }

        return $image;
    }
}
