<?php

namespace App\Dto\Image;

use App\Dto\Video\VideoDto;

class ImageOutputDto
{
    public int $id;
    public ?string $filename;
    public ?string $description;
    public bool $isFeatured;
    public ?int $parentId;
    /** @var VideoDto[] */
    public array $videos = [];
    public function __construct(
        int $id,
        ?string $filename,
        ?string $description,
        bool $isFeatured,
        ?int $parentId = null
    ) {
        $this->id = $id;
        $this->filename = $filename;
        $this->description = $description;
        $this->isFeatured = $isFeatured;
        $this->parentId = $parentId;
    }
}
