<?php

namespace App\Repository\Interface;

use App\Entity\Image;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface ImageRepositoryInterface extends BaseRepositoryInterface
{

    /** @return Image[] */
    public function findParentImages(): array;
    /** @return Image[] */
    public function findNonFeaturedAndParentImages(): array;
    /** @throws NotFoundHttpException */
    public function getById(int $id): Image;
}
