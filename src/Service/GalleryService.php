<?php

namespace App\Service;

use App\Dto\Gallery\GalleryItemDto;
use App\Dto\Video\VideoGalleryDto;
use App\Exception\GalleryItemNotFoundException;
use App\Service\Interface\GalleryDataProviderInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class GalleryService implements GalleryDataProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @throws Exception
     */
    public function getGalleryList(): array
    {
        $sql = "SELECT id, description, file_name FROM image 
                WHERE parent_id IS NULL AND is_published = 1
                ORDER BY id DESC";
        $result = $this->connection->fetchAllAssociative($sql);
        return array_map(fn ($item) => new GalleryItemDto(
            $item['id'],
            $item['description'],
            $item['file_name']
        ), $result);
    }

    /**
     * @throws Exception
     */
    public function getGalleryDetail(int $id): array
    {
        // Основное изображение
        $parent = $this->connection->fetchAssociative("SELECT id,
       description, file_name FROM image WHERE id = ?", [$id]);
        if (!$parent) {
            throw new GalleryItemNotFoundException('Изображение не найдено');
        }

        // Дочерние изображения
        $children = $this->connection->fetchAllAssociative("SELECT id, 
       description, file_name FROM image WHERE parent_id = ?", [$id]);
        // Видео
        $videos = $this->connection->fetchAllAssociative("SELECT id,
       title, youtube_url FROM video WHERE image_id = ?", [$id]);

        return [
            'parent' => new GalleryItemDto($parent['id'], $parent['description'], $parent['file_name']),
            'children' => array_map(fn ($item) => new GalleryItemDto(
                $item['id'],
                $item['description'],
                $item['file_name']
            ), $children),
            'videos' => array_map(fn ($video) => new VideoGalleryDto(
                $video['id'],
                $video['title'],
                $video['youtube_url']
            ), $videos)
        ];
    }
}
