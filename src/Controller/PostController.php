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
        private readonly VideoRepository $videoRepository,
        private readonly string          $galleryUploadDir,
    )
    {
    }

    #[Route('/', name: 'gallery_list', methods: ['GET'])]
    public function galleryList(Request $request): JsonResponse
    {
        $images = $this->imageRepository
            ->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL') // Только родительские изображения
            ->andWhere('i.isPublished = :isPublished')
            ->setParameter('isPublished', true)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();

        // Формируем ответ
        $result = [
            'status' => 'success',
            'images' => []
        ];

        foreach ($images as $image) {
            $result['images'][] = $this->extract($image);
        }

        return new JsonResponse($result);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function extract(Image $image): array
    {
        return [
            'id' => $image->getId(),
            'description' => $image->getDescription(),
            'thumbnail' => $this->galleryUploadDir . '/' . 'thumbs/' . $image->getFilename(),
        ];
    }

    #[Route('/{id}', name: 'gallery_detail', methods: ['GET'])]
    public function galleryDetail(int $id): JsonResponse
    {
// 1. Получаем родительскую картинку по её ID
        $parentImage = $this->imageRepository->find($id);

        Assert::that($parentImage)->notEmpty('Изображение не найдено');

        // 2. Получаем все дочерние изображения, связанные с этой родительской картинкой
        $childImages = $this->imageRepository->findBy(['parentId' => $parentImage]);

        // 3. Получаем все видео, связанные с этой родительской картинкой
        $videos = $this->videoRepository->findBy(['image' => $parentImage]);

        // 4. Формируем ответ в формате JSON
        $response = [
            'parentImage' => [
                'id' => $parentImage->getId(),
                'description' => $parentImage->getDescription(),
                'filename' => $parentImage->getFilename(),
            ],
            'childImages' => array_map(function ($childImage) {
                return [
                    'id' => $childImage->getId(),
                    'description' => $childImage->getDescription(),
                    'filename' => $childImage->getFilename(),
                ];
            }, $childImages),
            'videos' => array_map(function ($video) {
                return [
                    'id' => $video->getId(),
                    'title' => $video->getTitle(),
                    'youtubeUrl' => $video->getYoutubeUrl()
                ];
            }, $videos),
        ];

        return new JsonResponse($response);
    }

}
