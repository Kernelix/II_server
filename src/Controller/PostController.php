<?php

namespace App\Controller;

use App\Dto\Gallery\GalleryImageDto;
use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Repository\VideoRepository;
use Assert\Assert;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

final class PostController extends AbstractController
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly VideoRepository $videoRepository
    ) {
    }

    #[Route('/api/gallery', name: 'api_gallery_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gallery',
        description: 'Возвращает список всех опубликованных изображений галереи',
        summary: 'Получение списка изображений галереи',
        tags: ['Client API'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'images',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/GalleryImage')
                        )
                    ]
                )
            )
        ]
    )]
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
     * @param Image $image
     * @return GalleryImageDto
     */
    private function prepareImageData(Image $image): GalleryImageDto
    {
        $dto = new GalleryImageDto();
        $dto->id = $image->getId();
        $dto->description = $image->getDescription();
        $dto->filename = $image->getFilename();
        $dto->links = [
            'self' => $this->generateUrl('api_gallery_detail', ['id' => $image->getId()])
        ];

        return $dto;
    }

    #[Route('/api/gallery/{id}', name: 'api_gallery_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gallery/{id}',
        description: 'Возвращает информацию об изображении, его дочерних элементах и связанных видео',
        summary: 'Получение детальной информации об изображении',
        tags: ['Client API'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID основного изображения',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'parentImage',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'description', type: 'string'),
                                        new OA\Property(property: 'filename', type: 'string'),
                                        new OA\Property(
                                            property: '_links',
                                            properties: [
                                                new OA\Property(property: 'self', type: 'string')
                                            ],
                                            type: 'object'
                                        )
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'childImages',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/GalleryImage')
                                ),
                                new OA\Property(
                                    property: 'videos',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'title', type: 'string'),
                                            new OA\Property(property: 'youtubeUrl', type: 'string'),
                                            new OA\Property(
                                                property: '_links',
                                                properties: [
                                                    new OA\Property(property: 'source', type: 'string')
                                                ],
                                                type: 'object'
                                            )
                                        ]
                                    )
                                )
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(
                                    property: 'cache',
                                    properties: [
                                        new OA\Property(property: 'child_images', type: 'string'),
                                        new OA\Property(property: 'videos', type: 'string')
                                    ],
                                    type: 'object'
                                )
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Изображение не найдено',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Изображение не найдено')
                    ]
                )
            )
        ]
    )]
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
