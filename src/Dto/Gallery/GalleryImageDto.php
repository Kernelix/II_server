<?php

namespace App\Dto\Gallery;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'GalleryImage',
    title: 'GalleryImage',
    description: 'DTO для представления изображения галереи'
)]
class GalleryImageDto
{
    #[OA\Property(
        property: 'id',
        description: 'Уникальный идентификатор изображения',
        type: 'integer',
        example: 1
    )]
    public int $id;

    #[OA\Property(
        property: 'description',
        description: 'Описание содержимого изображения',
        type: 'string',
        example: 'Описание изображения',
        nullable: true
    )]
    public ?string $description;

    #[OA\Property(
        property: 'filename',
        description: 'Название файла изображения',
        type: 'string',
        example: 'image.jpg'
    )]
    public string $filename;

    #[OA\Property(
        property: '_links',
        description: 'Ссылки, связанные с изображением',
        properties: [
            new OA\Property(
                property: 'self',
                description: 'Ссылка на детальную страницу изображения',
                type: 'string',
                example: '/api/gallery/1'
            )
        ],
        type: 'object'
    )]
    public array $links;
}
