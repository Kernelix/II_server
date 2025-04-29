<?php

namespace App\Service;

use App\Entity\Video;
use App\Repository\Interface\ImageRepositoryInterface;
use App\Repository\Interface\VideoRepositoryInterface;
use App\Service\Interface\CachePurgerInterface;
use App\Service\Interface\VideoManagerInterface;
use InvalidArgumentException;

readonly class VideoManager implements VideoManagerInterface
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private CachePurgerInterface $cachePurger
    ) {
    }

    public function createVideo(
        array $data,
        ImageRepositoryInterface $imageRepository
    ): Video {
        $video = new Video();

        $this->processBaseData($video, $data, $imageRepository);
        $this->handleYoutubeUrl($video, $data['youtube_url'] ?? null);

        $this->videoRepository->save($video, true);
        $this->cachePurger->purgeAll();

        return $video;
    }

    public function updateVideo(
        Video $video,
        array $data,
        ImageRepositoryInterface $imageRepository
    ): void {
        $this->processBaseData($video, $data, $imageRepository);
        $this->handleYoutubeUrl($video, $data['youtube_url'] ?? null);
        $this->videoRepository->save($video, true);
        $this->cachePurger->purgeAll();
    }

    public function deleteVideo(Video $video): void
    {
        $this->videoRepository->remove($video, true);
        $this->cachePurger->purgeAll();
    }

    private function processBaseData(
        Video $video,
        array $data,
        ImageRepositoryInterface $imageRepository
    ): void {
        if (isset($data['title'])) {
            $video->setTitle($data['title']);
        }

        if (isset($data['image_id'])) {
            $this->handleImageAssociation($video, $data['image_id'], $imageRepository);
        }
    }

    private function handleImageAssociation(
        Video $video,
        ?int $imageId,
        ImageRepositoryInterface $imageRepository
    ): void {
        if (null === $imageId) {
            $video->setImage(null);
            return;
        }

        $image = $imageRepository->find($imageId);
        if (!$image) {
            throw new InvalidArgumentException('Изображение не найдено');
        }
        $video->setImage($image);
    }

    private function handleYoutubeUrl(Video $video, ?string $url): void
    {
        if (null !== $url) {
            $video->setYoutubeUrl($this->normalizeYoutubeUrl($url));
        }
    }

    private function normalizeYoutubeUrl(string $url): string
    {
        if (preg_match('/^https?:\/\/(?:www\.)?youtube\.com\/embed\//', $url)) {
            return $url;
        }

        if (preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('/youtu\.be\/([^\\?\\&]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        throw new InvalidArgumentException('Неверный формат YouTube ссылки');
    }
}
