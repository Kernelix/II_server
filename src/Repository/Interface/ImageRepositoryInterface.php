<?php

namespace App\Repository\Interface;

use App\Entity\Image;
use Doctrine\DBAL\LockMode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface ImageRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Image[] */
    public function findParentImages(): array;
    /** @return Image[] */
    public function findNonFeaturedAndParentImages(): array;
    /** @throws NotFoundHttpException */
    public function getById(int $id): Image;

    /**
     * @return Image[]
     */
    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array;

    /**
     * @param mixed $id
     * @param int|LockMode|null $lockMode
     * @param int|null $lockVersion
     */
    public function find(
        mixed $id,
        int|LockMode $lockMode = null,
        int $lockVersion = null
    ): ?Image;
}
