<?php

namespace App\Controller\Admin;

use App\Dto\Image\ImageOutputDto;
use App\Dto\Video\VideoDto;
use App\Entity\Image;
use App\Repository\ImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

class DashboardController extends AbstractController
{
    #[Route('/api/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Tag(name: 'Admin', description: 'Метод для получения данных для главной страницы админки')]
    #[OA\Response(
        response: 200,
        description: 'Returns dashboard stats',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'featured_images', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'uploaded_images', type: 'array', items: new OA\Items(type: 'object'))
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied'
    )]
    #[OA\Response(
        response: 401,
        description: 'Authentication required'
    )]
    public function index(ImageRepository $imageRepository): JsonResponse
    {
        // Автоматическая проверка доступа через #[IsGranted]
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $featuredImages = $imageRepository->findBy(['isFeatured' => true]);
        $uploadedImages = $imageRepository->findNonFeaturedAndParentImages();

        $resultFeaturedImages = [];
        foreach ($featuredImages as $featuredImage) {
            $resultFeaturedImages[] = $this->entityToDto($featuredImage);
        }

        $resultUploadedImage = [];
        foreach ($uploadedImages as $uploadedImage) {
            $resultUploadedImage[] = $this->entityToDto($uploadedImage);
        }

        return $this->json([
            'featured_images' =>  $resultFeaturedImages,
            'uploaded_images' => $resultUploadedImage,
        ]);
    }
    private function entityToDto(Image $image): ImageOutputDto
    {
        $dto = new ImageOutputDto(
            $image->getId(),
            $image->getFilename(),
            $image->getDescription(),
            $image->isFeatured(),
            $image->isPublished(),
            $image->getParentId()?->getId()
        );

        if (!$image->getVideos()->isEmpty()) {
            foreach ($image->getVideos() as $video) {
                $videoDto = new VideoDto();
                $videoDto->id = $video->getId();
                $videoDto->title = $video->getTitle();
                $videoDto->youtubeUrl = $video->getYoutubeUrl();
                $videoDto->imageId = $video->getImage()?->getId();
                $dto->videos[] = $videoDto;
            }
        }

        return $dto;
    }
}
