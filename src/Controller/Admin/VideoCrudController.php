<?php

namespace App\Controller\Admin;

use App\Entity\Video;
use App\Repository\Interface\ImageRepositoryInterface;
use App\Repository\Interface\VideoRepositoryInterface;
use App\Service\CachePurgerService;
use App\Service\Interface\DtoMapperInterface;
use App\Service\Interface\ErrorHandlerInterface;
use App\Service\Interface\MultipartParserInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/videos')]
#[OA\Tag(name: 'Admin Video', description: 'Управление видеозаписями в админ-панели')]
#[IsGranted('ROLE_ADMIN')]
class VideoCrudController extends AbstractController
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly CachePurgerService $cachePurger,
        private readonly DtoMapperInterface $dtoMapper,
        private readonly ErrorHandlerInterface $errorService,
        private readonly MultipartParserInterface $multipartParser
    ) {
    }

    #[Route('/', name: 'api_admin_videos_list', methods: ['GET'])]
    #[OA\Get(
        description: 'Возвращает массив всех видеозаписей в системе',
        summary: 'Получить список всех видео',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный запрос',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Video')
                        ),
                        new OA\Property(property: 'count', type: 'integer', example: 5)
                    ]
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $videos = $this->videoRepository->findAll();
        $result = [];
        foreach ($videos as $video) {
            $result[] = $this->dtoMapper->convertVideoToDto($video);
        }

        return $this->json([
            'status' => 'success',
            'data' => $result,
            'count' => count($videos)
        ]);
    }

    #[Route('/', name: 'api_admin_videos_create', methods: ['POST'])]
    #[OA\Post(
        description: 'Создает новую видеозапись. Все поля необязательные, но рекомендуется указать youtube_url.',
        summary: 'Добавить новое видео',
        requestBody: new OA\RequestBody(
            description: 'Данные видео (форма или JSON)',
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'title',
                                type: 'string',
                                example: 'Обзор продукта',
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'youtube_url',
                                type: 'string',
                                example: 'https://youtu.be/dQw4w9WgXcQ',
                                nullable: true
                            ),
                            new OA\Property(property: 'image_id', type: 'integer', example: 123, nullable: true)
                        ]
                    )
                ),
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'title',
                                type: 'string',
                                example: 'Обзор продукта',
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'youtube_url',
                                type: 'string',
                                example: 'https://youtu.be/dQw4w9WgXcQ',
                                nullable: true
                            ),
                            new OA\Property(property: 'image_id', type: 'integer', example: 123, nullable: true),
                            new OA\Property(property: 'description', type: 'string', nullable: true),
                        ]
                    )
                )
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Видео успешно создано',
                content: new OA\JsonContent(ref: '#/components/schemas/Video')
            ),
            new OA\Response(
                response: 400,
                description: 'Неверные данные',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Неверный формат YouTube ссылки')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Изображение не найдено',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Изображение не найдено')
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $request->request->all(); // Получаем данные формы
        $video = new Video();

        try {
            if (isset($data['title'])) {
                $video->setTitle($data['title']);
            }

            if (isset($data['youtube_url'])) {
                $video->setYoutubeUrl($this->normalizeYoutubeUrl($data['youtube_url']));
            }

            if (isset($data['image_id'])) {
                $image = $this->imageRepository->find($data['image_id']);
                if (!$image) {
                    return $this->json(['error' => 'Изображение не найдено'], Response::HTTP_NOT_FOUND);
                }
                $video->setImage($image);
            }

            $this->videoRepository->save($video, true);
            $this->cachePurger->purgeAll();

            return $this->json(
                $this->dtoMapper->convertVideoToDto($video),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_admin_videos_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Получить видео по ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID видео',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Данные видео',
                content: new OA\JsonContent(ref: '#/components/schemas/Video')
            ),
            new OA\Response(
                response: 404,
                description: 'Видео не найдено',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Видео не найдено')
                    ]
                )
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $video = $this->getVideoOrFail($id);

        return $this->json($this->dtoMapper->convertVideoToDto($video));
    }

    #[Route('/{id}', name: 'api_admin_videos_update', methods: ['PATCH'])]
    #[OA\Patch(
        description: 'Частичное обновление данных видео. Можно передать только те поля, которые нужно изменить.',
        summary: 'Обновить видео',
        requestBody: new OA\RequestBody(
            description: 'Данные для обновления',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                    new OA\Property(property: 'title', type: 'string', nullable: true),
                    new OA\Property(property: 'youtube_url', type: 'string', nullable: true),
                    new OA\Property(property: 'image_id', type: 'integer', nullable: true)
                    ],
                    type: 'object'
                )
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID видео',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Видео обновлено',
                content: new OA\JsonContent(ref: '#/components/schemas/Video')
            ),
            new OA\Response(
                response: 400,
                description: 'Неверные данные',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Видео или изображение не найдено',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $video = $this->getVideoOrFail($id);

        $parsedData = $this->multipartParser->parse($request);
        $data = $parsedData['data'];

        try {
            // Обновление полей
            if (isset($data['title'])) {
                $video->setTitle($data['title']);
            }

            if (isset($data['youtube_url'])) {
                $video->setYoutubeUrl($this->normalizeYoutubeUrl($data['youtube_url']));
            }

            if (isset($data['image_id'])) {
                $this->handleImageAssociation($video, $data['image_id']);
            }

            $this->videoRepository->save($video, true);
            $this->cachePurger->purgeAll();
            return $this->json($this->dtoMapper->convertVideoToDto($video));
        } catch (\InvalidArgumentException $e) {
            return $this->errorService->jsonError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception) {
            return $this->errorService->jsonError('Server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    #[Route('/{id}', name: 'api_admin_videos_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Удалить видео',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID видео',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Видео удалено'
            ),
            new OA\Response(
                response: 404,
                description: 'Видео не найдено',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Видео не найдено')
                    ]
                )
            )
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $video = $this->getVideoOrFail($id);

        $this->videoRepository->remove($video, true);
        $this->cachePurger->purgeAll();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Нормализует YouTube URL в embed-формат
     */
    private function normalizeYoutubeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (preg_match('/^https?:\/\/(?:www\.)?youtube\.com\/embed\//', $url)) {
            return $url;
        }

        if (preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('/youtu\.be\/([^\\?\\&]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        throw new \InvalidArgumentException('Неверный формат YouTube ссылки');
    }



    private function handleImageAssociation(Video $video, $imageId): void
    {
        if ($imageId === null || $imageId === '') {
            $video->setImage(null);
            return;
        }

        $image = $this->imageRepository->find((int)$imageId);
        if (!$image) {
            throw new \InvalidArgumentException('Image not found');
        }
        $video->setImage($image);
    }

    private function getVideoOrFail(int $id): Video
    {
        $video = $this->videoRepository->find($id);
        if (!$video) {
            throw new NotFoundHttpException('Видео не найдено');
        }
        return $video;
    }
}
