<?php

namespace App\Dto\Video;

use App\Dto\Gallery\GalleryImageDto;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Video",
    description: "Видеозапись",
    type: "object"
)]
class VideoDto
{
    #[OA\Property(property: "id", type: "integer", example: 1)]
    public ?int $id = null;

    #[OA\Property(
        property: "title",
        type: "string",
        example: "Обзор продукта",
        nullable: true
    )]
    public ?string $title = null;

    #[OA\Property(
        property: "youtube_url",
        type: "string",
        example: "https://www.youtube.com/embed/dQw4w9WgXcQ",
        nullable: true
    )]
    public ?string $youtubeUrl = null;

    #[OA\Property(
        property: "image_id",
        type: "integer",
        example: 123,
        nullable: true
    )]
    public ?int $imageId = null;

    #[OA\Property(
        property: "image",
        ref: "#/components/schemas/GalleryImage",
        type: "object",
        nullable: true // Указываем ссылку на схему GalleryImage
    )]
    public ?GalleryImageDto $image = null;
}
