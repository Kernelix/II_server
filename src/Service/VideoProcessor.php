<?php

namespace App\Service;

use App\Entity\Image;
use App\Entity\Video;
use App\Repository\VideoRepository;
use App\Service\Interface\VideoProcessorInterface;

class VideoProcessor implements VideoProcessorInterface
{
    public function __construct(
        private readonly videoRepository $videoRepository
    ) {
    }
    public function processVideos(Image $image, $videosData): void
    {
        if (is_string($videosData)) {
            $videosData = json_decode($videosData, true);
        }

        foreach ($videosData as $videoData) {
            $video = isset($videoData['id'])
                ? $this->videoRepository->find($videoData['id'])
                : new Video();

            $video->setTitle($videoData['title'] ?? '');
            $video->setYoutubeUrl($videoData['youtube_url'] ?? '');
            $video->setImage($image->getParentId() ?: $image);

            $this->videoRepository->save($video, false);
        }
    }
}
