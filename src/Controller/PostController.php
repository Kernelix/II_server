<?php

namespace App\Controller;

use App\Repository\ImageRepository;
use App\Repository\VideoRepository;
use Assert\Assert;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class PostController extends AbstractController
{
    private const int CACHE_TTL = 2629800; // 1 месяц в секундах

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly VideoRepository $videoRepository,
        private readonly CacheItemPoolInterface $cache
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
        $cacheKey = 'gallery_list_query';

        // Используем более эффективный метод get()
        $data = $this->cache->get($cacheKey, function (CacheItem $item) {
            $item->expiresAfter(self::CACHE_TTL);

            if ($this->cache instanceof TagAwareCacheInterface) {
                $item->tag(['gallery_all']);
            }

            return $this->generateGalleryData();
        });

        $etag = $this->cache->get(
            'gallery_list_query_etag',
            fn () => hash('xxh128', json_encode($data))
        );

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
        // Ключи кэша
        $cacheKey = "gallery_detail_{$id}";
        $etagKey = "{$cacheKey}_etag";

        // Пытаемся получить данные из кэша
        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($cacheKey, $etagKey, $id) {
            $item->expiresAfter(self::CACHE_TTL);

            $startTime = microtime(true);

            // Оптимизированный запрос для родительского изображения
            $parentImage = $this->imageRepository->createQueryBuilder('i')
                ->select('i.id', 'i.description', 'i.fileName')
                ->where('i.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->enableResultCache(self::CACHE_TTL, "parent_image_{$id}")
                ->getOneOrNullResult();

            Assert::that($parentImage)->notEmpty('Изображение не найдено');

            // Параллельно получаем дочерние элементы и видео
            $childImages = $this->imageRepository->createQueryBuilder('c')
                ->select('c.id', 'c.description', 'c.fileName')
                ->where('c.parentId = :parentId')
                ->setParameter('parentId', $id)
                ->getQuery()
                ->enableResultCache(self::CACHE_TTL, "child_images_{$id}")
                ->getArrayResult();

            $videos = $this->videoRepository->createQueryBuilder('v')
                ->select('v.id', 'v.title', 'v.youtubeUrl')
                ->where('v.image = :imageId')
                ->setParameter('imageId', $id)
                ->getQuery()
                ->enableResultCache(self::CACHE_TTL, "videos_{$id}")
                ->getArrayResult();

            $data = [
                'status' => 'success',
                'data' => [
                    'parentImage' => $this->prepareImageData($parentImage),
                    'childImages' => array_map([$this, 'prepareImageData'], $childImages),
                    'videos' => array_map(fn ($v) => [
                        'id' => $v['id'],
                        'title' => $v['title'],
                        'youtubeUrl' => $v['youtubeUrl'],
                        'links' => ['source' => $v['youtubeUrl']]
                    ], $videos)
                ],
                'meta' => [
                    'query_time' => round(microtime(true) - $startTime, 4) . 's',
                    'cache' => [
                        'key' => $cacheKey,
                        'ttl' => self::CACHE_TTL
                    ]
                ]
            ];

            // Сохраняем ETag отдельно
            $this->cache->save(
                $this->cache->getItem($etagKey)
                    ->set(hash('xxh128', json_encode($data)))
                    ->expiresAfter(self::CACHE_TTL)
            );

            return $data;
        });

        // Проверка ETag
        $etag = $this->cache->get($etagKey, fn () => hash('xxh128', json_encode($cachedData)));

        if ($request->isMethodCacheable() && $request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304);
        }

        return $this->buildCachedResponse($cachedData, $etag);
    }

    private function prepareImageData(array $image): array
    {
        return [
            'id' => $image['id'],
            'description' => $image['description'],
            'filename' => $image['fileName'],
            'links' => [
                'self' => $this->generateUrl('api_gallery_detail', ['id' => $image['id']])
            ]
        ];
    }


    /**
     * @return array<string, mixed>
     */
    private function generateGalleryData(): array
    {
        $query = $this->imageRepository
            ->createQueryBuilder('i')
            ->select('i.id', 'i.description', 'i.fileName')
            ->andWhere('i.parentId IS NULL')
            ->andWhere('i.isPublished = :isPublished')
            ->setParameter('isPublished', true)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->enableResultCache(self::CACHE_TTL, 'gallery_list_query_db');

        $images = $query->getArrayResult();

        return [
            'status' => 'success',
            'data' => [
                'images' => array_map(fn (array $img) => [
                    'id' => $img['id'],
                    'description' => $img['description'],
                    'filename' => $img['fileName'],
                    'links' => [
                        'self' => $this->generateUrl('api_gallery_detail', ['id' => $img['id']])
                    ]
                ], $images)
            ],
            'meta' => [
                'count' => count($images),
                'cache' => [
                    'key' => 'gallery_list_query',
                    'ttl' => self::CACHE_TTL
                ]
            ]
        ];
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
