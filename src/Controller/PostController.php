<?php

namespace App\Controller;

use App\Dto\Gallery\GalleryImageDto;
use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Repository\VideoRepository;
use Assert\Assert;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

final class PostController extends AbstractController
{
    private const int CACHE_TTL = 2629800; // 1 месяц в секундах

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly VideoRepository $videoRepository
    ) {
    }

    #[Route('/api/gallery', name: 'api_gallery_list', methods: ['GET', 'HEAD'])]
    #[OA\Get(
        path: '/api/gallery',
        operationId: 'getGalleryList',
        description: 'Возвращает список всех опубликованных изображений галереи (GET) или проверяет их наличие (HEAD)',
        summary: 'Получение списка изображений галереи',
        tags: ['Client API'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ (GET)',
                headers: [
                    new OA\Header(
                        header: 'Cache-Control',
                        description: 'Кэширование на 1 месяц',
                        schema: new OA\Schema(type: 'string')
                    ),
                    new OA\Header(
                        header: 'ETag',
                        description: 'Хэш контента для валидации',
                        schema: new OA\Schema(type: 'string')
                    )
                ],
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            example: 'success'
                        ),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'images',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(
                                                property: 'id',
                                                type: 'integer',
                                                example: 1
                                            ),
                                            new OA\Property(
                                                property: 'description',
                                                type: 'string',
                                                example: 'самисмимс'
                                            ),
                                            new OA\Property(
                                                property: 'filename',
                                                type: 'string',
                                                example: '67e92f18c6a32.jpg'
                                            ),
                                            new OA\Property(
                                                property: 'links',
                                                properties: [
                                                    new OA\Property(
                                                        property: 'self',
                                                        type: 'string',
                                                        example: '/api/gallery/1'
                                                    )
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
                                    property: 'count',
                                    type: 'integer',
                                    example: 1
                                ),
                                new OA\Property(
                                    property: 'cache',
                                    properties: [
                                        new OA\Property(
                                            property: 'key',
                                            type: 'string',
                                            example: 'gallery_list_query'
                                        ),
                                        new OA\Property(
                                            property: 'status',
                                            type: 'string',
                                            example: 'enabled'
                                        ),
                                        new OA\Property(
                                            property: 'ttl',
                                            type: 'integer',
                                            example: 2629800
                                        )
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
                response: 304,
                description: 'Контент не изменился (по ETag)',
                headers: [
                    new OA\Header(
                        header: 'ETag',
                        description: 'Актуальный хэш контента',
                        schema: new OA\Schema(type: 'string')
                    )
                ]
            )
        ]
    )]
    #[OA\Head(
        path: '/api/gallery',
        operationId: 'headGalleryList',
        description: 'Проверка состояния списка изображений галереи (возвращает только заголовки)',
        summary: 'Проверка списка изображений',
        tags: ['Client API'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ (HEAD)',
                headers: [
                    new OA\Header(
                        header: 'Cache-Control',
                        description: 'Кэширование на 1 месяц',
                        schema: new OA\Schema(type: 'string')
                    ),
                    new OA\Header(
                        header: 'ETag',
                        description: 'Хэш контента для валидации',
                        schema: new OA\Schema(type: 'string')
                    )
                ]
            ),
            new OA\Response(
                response: 304,
                description: 'Контент не изменился (по ETag)',
                headers: [
                    new OA\Header(
                        header: 'ETag',
                        description: 'Актуальный хэш контента',
                        schema: new OA\Schema(type: 'string')
                    )
                ]
            )
        ]
    )]
    public function galleryList(Request $request): JsonResponse
    {
        $query = $this->imageRepository
            ->createQueryBuilder('i')
            ->andWhere('i.parentId IS NULL')
            ->andWhere('i.isPublished = :isPublished')
            ->setParameter('isPublished', true)
            ->orderBy('i.id', 'DESC')
            ->getQuery();



        // Генерируем уникальный ключ кэша
        $cacheKey = 'gallery_list_query';

        // Включаем кэширование с проверкой доступности
        try {
            $query->enableResultCache(self::CACHE_TTL, $cacheKey);
            $cacheStatus = 'enabled';
        } catch (\Exception $e) {
            $cacheStatus = 'disabled';
        }

        $images = $query->getResult();

        $data = [
            'status' => 'success',
            'data' => [
                'images' => array_map([$this, 'prepareImageData'], $images)
            ],
            'meta' => [
                'count' => count($images),
                'cache' => [
                    'key' => $cacheKey,
                    'status' => $cacheStatus,
                    'ttl' => self::CACHE_TTL
                ]
            ]
        ];

        $etag = $this->generateEtag($data);

        if ($request->isMethodCacheable() && $request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304);
        }


        return $this->buildCachedResponse($data, $etag);
    }

    #[Route('/api/gallery/{id}', name: 'api_gallery_detail', methods: ['GET', 'HEAD'])]
    #[OA\Get(
        path: '/api/gallery/{id}',
        operationId: 'getGalleryDetail',
        description: 'Возвращает информацию об изображении,
         его дочерних элементах и связанных видео (GET) или проверяет их наличие (HEAD)',
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
                description: 'Успешный ответ (GET)',
                headers: [
                    new OA\Header(
                        header: 'Cache-Control',
                        description: 'Кэширование на 1 месяц',
                        schema: new OA\Schema(type: 'string')
                    ),
                    new OA\Header(
                        header: 'ETag',
                        description: 'Хэш контента для валидации',
                        schema: new OA\Schema(type: 'string')
                    )
                ],
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            example: 'success'
                        ),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'parentImage',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'description', type: 'string', example: 'Описание'),
                                        new OA\Property(property: 'filename', type: 'string', example: 'image.jpg'),
                                        new OA\Property(
                                            property: 'links',
                                            properties: [
                                                new OA\Property(
                                                    property: 'self',
                                                    type: 'string',
                                                    example: '/api/gallery/1'
                                                )
                                            ],
                                            type: 'object'
                                        )
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'childImages',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'description', type: 'string'),
                                            new OA\Property(property: 'filename', type: 'string'),
                                            new OA\Property(
                                                property: 'links',
                                                properties: [
                                                    new OA\Property(property: 'self', type: 'string')
                                                ],
                                                type: 'object'
                                            )
                                        ]
                                    )
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
                                                property: 'links',
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
                                        new OA\Property(
                                            property: 'child_images',
                                            type: 'string',
                                            example: 'child_images_1'
                                        ),
                                        new OA\Property(property: 'videos', type: 'string', example: 'videos_1')
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
                response: 304,
                description: 'Контент не изменился (по ETag)',
                headers: [
                    new OA\Header(
                        header: 'ETag',
                        description: 'Актуальный хэш контента',
                        schema: new OA\Schema(type: 'string')
                    )
                ]
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
    #[OA\Head(
        path: '/api/gallery/{id}',
        operationId: 'headGalleryDetail',
        description: 'Проверка состояния изображения и связанных данных (возвращает только заголовки)',
        summary: 'Проверка детальной информации об изображении',
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
                description: 'Успешный ответ (HEAD)',
                headers: [
                    new OA\Header(
                        header: 'Cache-Control',
                        description: 'Кэширование на 1 месяц',
                        schema: new OA\Schema(type: 'string')
                    ),
                    new OA\Header(
                        header: 'ETag',
                        description: 'Хэш контента для валидации',
                        schema: new OA\Schema(type: 'string')
                    )
                ]
            ),
            new OA\Response(
                response: 304,
                description: 'Контент не изменился (по ETag)',
                headers: [
                    new OA\Header(
                        header: 'ETag',
                        description: 'Актуальный хэш контента',
                        schema: new OA\Schema(type: 'string')
                    )
                ]
            ),
            new OA\Response(
                response: 422,
                description: 'Изображение не найдено'
            )
        ]
    )]
    public function galleryDetail(Request $request, int $id): JsonResponse
    {
        $parentImage = $this->imageRepository->find($id);
        Assert::that($parentImage)->notEmpty('Изображение не найдено');

        // Кэшируем дочерние изображения
        $childImages = $this->imageRepository->createQueryBuilder('c')
            ->andWhere('c.parentId = :parentId')
            ->setParameter('parentId', $parentImage)
            ->getQuery()
            ->enableResultCache(self::CACHE_TTL, 'child_images_' . $id)
            ->getResult();

        // Кэшируем видео
        $videos = $this->videoRepository->createQueryBuilder('v')
            ->andWhere('v.image = :image')
            ->setParameter('image', $parentImage)
            ->getQuery()
            ->enableResultCache(self::CACHE_TTL, 'videos_' . $id)
            ->getResult();

        $data = [
            'status' => 'success',
            'data' => [
                'parentImage' => $this->prepareImageData($parentImage),
                'childImages' => array_map([$this, 'prepareImageData'], $childImages),
                'videos' => array_map(function ($video) {
                    return [
                        'id' => $video->getId(),
                        'title' => $video->getTitle(),
                        'youtubeUrl' => $video->getYoutubeUrl(),
                        'links' => [
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
        ];

        $etag = $this->generateEtag($data);

        if ($request->isMethodCacheable() && $request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304);
        }


        return $this->buildCachedResponse($data, $etag);
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

    /**
     * Генерирует ETag для данных ответа
     */
    private function generateEtag(array $data): string
    {
        return hash('sha256', json_encode($data));
    }

    /**
     * Создает JsonResponse с настройками кэширования
     */
    private function buildCachedResponse(array $data, string $etag): JsonResponse
    {
        return (new JsonResponse($data))
            ->setEtag($etag)
            ->setPublic()
            ->setMaxAge(self::CACHE_TTL)
            ->setSharedMaxAge(self::CACHE_TTL);
    }
}
