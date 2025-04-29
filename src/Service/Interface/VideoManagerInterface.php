<?php

namespace App\Service\Interface;

use App\Entity\Video;
use App\Repository\Interface\ImageRepositoryInterface;

interface VideoManagerInterface
{
    public function createVideo(array $data, ImageRepositoryInterface $imageRepository): Video;
    public function updateVideo(Video $video, array $data, ImageRepositoryInterface $imageRepository): void;
    public function deleteVideo(Video $video): void;
}
