<?php

namespace App\Service;

use App\Dto\Gallery\GalleryImageDto;
use App\Dto\Image\ImageOutputDto;
use App\Dto\Video\VideoDto;
use App\Entity\Image;
use App\Entity\Video;
use App\Service\Interface\DtoMapperInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DtoMapperService implements DtoMapperInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $router
    ) {
    }

    public function convertImageToDto(Image $image): ImageOutputDto
    {
        $dto = new ImageOutputDto(
            $image->getId(),
            $image->getFilename(),
            $image->getDescription(),
            $image->isFeatured(),
            $image->isPublished(),
            $image->getParentId()?->getId()
        );

        // Обработка дочерних изображений
        foreach ($image->getImages() as $childImage) {
            $dto->childrens[] = $this->convertImageToDto($childImage);
        }

        // Обработка видео
        foreach ($image->getVideos() as $video) {
            $videoDto = new VideoDto();
            $videoDto->id = $video->getId();
            $videoDto->title = $video->getTitle();
            $videoDto->youtubeUrl = $video->getYoutubeUrl();
            $videoDto->imageId = $video->getImage()?->getId();

            $dto->videos[] = $videoDto;
        }

        return $dto;
    }

    public function convertVideoToDto(Video $video): VideoDto
    {
        $dto = new VideoDto();
        $dto->id = $video->getId();
        $dto->title = $video->getTitle();
        $dto->youtubeUrl = $video->getYoutubeUrl();
        $dto->imageId = $video->getImage()?->getId();

        if ($video->getImage()) {
            $imageDto = new GalleryImageDto();
            $imageDto->id = $video->getImage()->getId();
            $imageDto->filename = $video->getImage()->getFilename();
            $imageDto->description = $video->getImage()->getDescription();
            $imageDto->links = [
                'self' => $this->router->generate(
                    'api_admin_images_show',
                    ['id' => $video->getImage()->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ];

            $dto->image = $imageDto;
        }

        return $dto;
    }
}
