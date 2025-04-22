<?php

namespace App\Service;

use App\Entity\Image;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Service\Interface\{
    ImageManagerInterface,
    ImageStorageInterface,
    VideoProcessorInterface,
    CachePurgerInterface
};
use Doctrine\ORM\EntityManagerInterface;

class ImageManager implements ImageManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageStorageInterface $storage,
        private readonly VideoProcessorInterface $videoProcessor,
        private readonly CachePurgerInterface $cachePurger
    ) {
    }

    public function createImage(UploadedFile $file, ?string $description, bool $isFeatured): Image
    {
        $fileName = $this->storage->upload($file);
        $image = new Image();
        $image
            ->setFilename($fileName)
            ->setDescription($description)
            ->setIsFeatured($isFeatured);
        $this->em->persist($image);
        $this->em->flush();
        $this->cachePurger->purgeAll();
        return $image;
    }

    public function updateImage(Image $image, array $data, ?UploadedFile $file): void
    {
        if ($file) {
            $this->storage->delete($image->getFilename());
            $image->setFilename($this->storage->upload($file));
        }

        if (isset($data['description'])) {
            $image->setDescription($data['description']);
        }

        if (isset($data['isFeatured'])) {
            $image->setIsFeatured((bool)$data['isFeatured']);
        }

        if (!empty($data['videos'])) {
            $this->videoProcessor->processVideos($image, $data['videos']);
        }

        $this->em->flush();
        $this->cachePurger->purgeAll();
    }

    public function deleteImage(Image $image): void
    {
        $this->storage->delete($image->getFilename());
        $this->em->remove($image);
        $this->em->flush();
        $this->cachePurger->purgeAll();
    }

    public function togglePublish(Image $image): void
    {
        $image->setIsPublished(!$image->isPublished());
        $this->em->flush();
        $this->cachePurger->purgeAll();
    }

    public function updateMetadata(
        Image $image,
        ?string $description,
        bool $isFeatured,
        ?bool $isPublished
    ): void {
        if ($description !== null) {
            $image->setDescription($description);
        }

        $image->setIsFeatured($isFeatured);

        if ($isPublished !== null) {
            $image->setIsPublished($isPublished);
        }

        $this->em->flush();
        $this->cachePurger->purgeAll();
    }
}
