<?php

namespace App\Repository\Interface;

use App\Entity\Video;
use Doctrine\DBAL\LockMode;

interface VideoRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Video[]
     */
    public function findAll(): array;

    /**
     * @param mixed $id
     * @param int|LockMode|null $lockMode
     * @param int|null $lockVersion
     */
    public function find(mixed $id, int|LockMode $lockMode = null, int $lockVersion = null): ?Video;
}
