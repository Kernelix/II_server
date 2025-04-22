<?php

namespace App\Service\Interface;

use App\Entity\Image;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImageManagerInterface
{
    public function createImage(UploadedFile $file, ?string $description, bool $isFeatured): Image;
    public function updateImage(Image $image, array $data, ?UploadedFile $file): void;
    public function deleteImage(Image $image): void;
    public function togglePublish(Image $image): void;
    public function updateMetadata(
        Image $image,
        ?string $description,
        bool $isFeatured,
        ?bool $isPublished
    ): void;
}
