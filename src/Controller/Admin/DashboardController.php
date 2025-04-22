<?php

namespace App\Controller\Admin;

use App\Repository\Interface\ImageRepositoryInterface;
use App\Service\Interface\DtoMapperInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DtoMapperInterface $dtoMapper,
        private readonly ImageRepositoryInterface $imageRepository
    ) {
    }
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
    public function index(): JsonResponse
    {
        // Автоматическая проверка доступа через #[IsGranted]
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $featuredImages = $this->imageRepository->findBy(['isFeatured' => true]);
        $uploadedImages = $this->imageRepository->findNonFeaturedAndParentImages();

        $resultFeaturedImages = [];
        foreach ($featuredImages as $featuredImage) {
            $resultFeaturedImages[] = $this->dtoMapper->convertImageToDto($featuredImage);
        }

        $resultUploadedImage = [];
        foreach ($uploadedImages as $uploadedImage) {
            $resultUploadedImage[] = $this->dtoMapper->convertImageToDto($uploadedImage);
        }

        return $this->json([
            'featured_images' =>  $resultFeaturedImages,
            'uploaded_images' => $resultUploadedImage,
        ]);
    }
}
