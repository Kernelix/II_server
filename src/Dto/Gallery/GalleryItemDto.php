<?php

namespace App\Dto\Gallery;

readonly class GalleryItemDto
{
    public function __construct(public int $id, public ?string $description, public string $filename)
    {
    }
}
