<?php

namespace App\Controller;


use App\Entity\Image;
use App\Repository\ImageRepository;
use Assert\Assert;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class GalleryController extends AbstractController
{


    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly string          $galleryUploadDir,
    )
    {
    }

    #[Route('/', name: 'gallery_list', methods: ['GET'])]
    public function galleryList(Request $request): JsonResponse
    {

            $page = (int)$request->query->get('page');
            $limit = (int)$request->query->get('limit');

            Assert::lazy()
                ->that($page, 'page')
                ->notEmpty('Страница не может быть пустой')
                ->integer('int', 'Страница должна быть числом')
                ->lessOrEqualThan(100, 'Превышено максимальное количество страниц (100)')
                ->range(1, 50, 'Лимит должен быть от 1 до 50')
                ->that($limit, 'limit')
                ->notEmpty('Лимит не может быть пустым')
                ->integer('int', 'Лимит должен быть числом')
                ->range(1, 50, 'Лимит должен быть от 1 до 50')
                ->verifyNow();


            // Получаем пагинированный список
            $query = $this->imageRepository
                ->createQueryBuilder('i')
                ->orderBy('i.id', 'DESC')
                ->getQuery();

            $paginator = new Paginator($query);
            $paginator
                ->getQuery()
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);

            // Формируем ответ
            $result = [
                'status' => 'success',
                'page' => $page,
                'total_pages' => ceil(count($paginator) / $limit),
                'total_items' => count($paginator),
                'images' => []
            ];

            foreach ($paginator as $image) {
                $result['images'][] = $this->extract($image);
            }


            return new JsonResponse(
                $result
            );
    }



    private function extract(Image $image): array
    {
        return [
            'id' => $image->getId(),
            'description' => $image->getDescription(),
            'url' => $this->galleryUploadDir . '/' . $image->getFilename(),
            'thumbnail' => $this->galleryUploadDir . '/' . 'thumbs/' . $image->getFilename(),
        ];
    }
}
