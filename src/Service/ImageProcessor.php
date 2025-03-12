<?php
namespace App\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class ImageProcessor
{
    private $imagine;

    public function __construct()
    {
        $this->imagine = new Imagine();
    }

    /**
     * Создаёт миниатюру изображения.
     *
     * @param string $sourcePath Путь к исходному изображению.
     * @param string $targetPath Путь для сохранения миниатюры.
     * @param int $width Ширина миниатюры.
     * @param int $height Высота миниатюры.
     */
    public function createThumbnail(string $sourcePath, string $targetPath, int $width, int $height): void
    {
        $image = $this->imagine->open($sourcePath);
        $image->thumbnail(new Box($width, $height), ImageInterface::THUMBNAIL_OUTBOUND)
            ->save($targetPath);
    }
}