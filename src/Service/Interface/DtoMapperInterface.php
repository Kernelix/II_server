<?php
namespace App\Service\Interface;

use App\Dto\Image\ImageOutputDto;
use App\Dto\Video\VideoDto;
use App\Entity\Image;
use App\Entity\Video;

interface DtoMapperInterface
{
    /**
     * Конвертирует сущность Image в DTO
     */
    public function convertImageToDto(Image $image): ImageOutputDto;

    /**
     * Конвертирует сущность Video в DTO
     * @param Video $video Сущность видео для конвертации
     * @return VideoDto
     */
    public function convertVideoToDto(Video $video): VideoDto;
}