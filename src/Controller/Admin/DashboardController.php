<?php

namespace App\Controller\Admin;

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

        // Нормализация данных для API
        $normalizeImage = function ($image) {
            return [
                'id' => $image->getId(),
                'title' => $image->getTitle(),
                'url' => $image->getUrl(),
                'is_featured' => $image->isFeatured(),
            ];
        };

        return $this->json([
            'featured_images' => array_map($normalizeImage, $featuredImages),
            'uploaded_images' => array_map($normalizeImage, $uploadedImages),
        ]);
    }
}
