<?php

namespace App\Service\Interface;

use App\Exception\GalleryItemNotFoundException;

interface GalleryDataProviderInterface
{
    public function getGalleryList(): array;

    /**
     * @throws GalleryItemNotFoundException
     */
    public function getGalleryDetail(int $id): array;
}