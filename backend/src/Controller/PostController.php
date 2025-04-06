<?php

namespace App\Controller;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Repository\VideoRepository;
use Assert\Assert;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly VideoRepository $videoRepository
    ) {
    }

    #[Route('/api/gallery', name: 'api_gallery_list', methods: ['GET'])]
    public function galleryList(Request $request): JsonResponse
    {
        $images = $this->imageRepository
            ->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL')
            ->andWhere('i.isPublished = :isPublished')
            ->setParameter('isPublished', true)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->enableResultCache(2629800000, 'gallery_list_query')
            ->getResult();

        return $this->json([
            'status' => 'success',
            'data' => [
                'images' => array_map([$this, 'prepareImageData'], $images)
            ],
            'meta' => [
                'count' => count($images),
                'cache_key' => 'gallery_list_query'
            ]
        ]);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function prepareImageData(Image $image): array
    {
        return [
            'id' => $image->getId(),
            'description' => $image->getDescription(),
            'filename' => $image->getFilename(),
            '_links' => [
                'self' => $this->generateUrl('api_gallery_detail', ['id' => $image->getId()])
            ]
        ];
    }

    #[Route('/api/gallery/{id}', name: 'api_gallery_detail', methods: ['GET'])]
    public function galleryDetail(int $id): JsonResponse
    {
        $parentImage = $this->imageRepository->find($id);
        Assert::that($parentImage)->notEmpty('Изображение не найдено');

        $childImages = $this->imageRepository->createQueryBuilder('c')
            ->andWhere('c.parentId = :parentId')
            ->setParameter('parentId', $parentImage)
            ->getQuery()
            ->enableResultCache(2629800000, 'child_images_' . $id)
            ->getResult();

        $videos = $this->videoRepository->createQueryBuilder('v')
            ->andWhere('v.image = :image')
            ->setParameter('image', $parentImage)
            ->getQuery()
            ->enableResultCache(2629800000, 'videos_' . $id)
            ->getResult();

        return $this->json([
            'status' => 'success',
            'data' => [
                'parentImage' => $this->prepareImageData($parentImage),
                'childImages' => array_map([$this, 'prepareImageData'], $childImages),
                'videos' => array_map(function ($video) {
                    return [
                        'id' => $video->getId(),
                        'title' => $video->getTitle(),
                        'youtubeUrl' => $video->getYoutubeUrl(),
                        '_links' => [
                            'source' => $video->getYoutubeUrl()
                        ]
                    ];
                }, $videos)
            ],
            'meta' => [
                'cache' => [
                    'child_images' => 'child_images_' . $id,
                    'videos' => 'videos_' . $id
                ]
            ]
        ]);
    }
}
