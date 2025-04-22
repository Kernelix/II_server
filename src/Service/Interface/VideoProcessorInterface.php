<?php

namespace App\Service\Interface;

use App\Entity\Image;

interface VideoProcessorInterface
{
    /** @param array<string, mixed> $videosData */
    public function processVideos(Image $image, array $videosData): void;
}
