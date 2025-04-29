<?php

namespace App\Dto\Video;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "VideoRequest",
    description: "Данные для создания/обновления видео",
    required: ["youtube_url"],
    type: "object"
)]
class VideoRequestDto
{
    #[OA\Property(
        property: "title",
        type: "string",
        maxLength: 255,
        example: "Обзор продукта",
        nullable: true
    )]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[OA\Property(
        property: "youtube_url",
        type: "string",
        format: "uri",
        example: "https://youtu.be/dQw4w9WgXcQ"
    )]
    #[Assert\NotBlank]
    #[Assert\Url]
    public string $youtube_url;

    #[OA\Property(
        property: "image_id",
        type: "integer",
        example: 123,
        nullable: true
    )]
    #[Assert\PositiveOrZero]
    public ?int $image_id = null;
}
