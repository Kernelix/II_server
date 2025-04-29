<?php

namespace App\Dto\Video;

readonly class VideoGalleryDto
{
    public function __construct(
        public int $id,
        public string $title,
        public string $youtubeUrl
    ) {
    }
}
