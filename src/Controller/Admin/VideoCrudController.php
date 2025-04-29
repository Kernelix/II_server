<?php

namespace App\Controller\Admin;

use App\Entity\Video;
use App\Repository\Interface\ImageRepositoryInterface;
use App\Repository\Interface\VideoRepositoryInterface;
use App\Service\Interface\DtoMapperInterface;
use App\Service\Interface\ErrorHandlerInterface;
use App\Service\Interface\MultipartParserInterface;
use App\Service\Interface\VideoManagerInterface;
use Assert\Assert;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/videos')]
#[OA\Tag(name: 'Admin Video', description: 'Управление видеозаписями в админ-панели')]
#[IsGranted('ROLE_ADMIN')]
class VideoCrudController extends AbstractController
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly DtoMapperInterface $dtoMapper,
        private readonly ErrorHandlerInterface $errorService,
        private readonly MultipartParserInterface $multipartParser,
        private readonly VideoManagerInterface $videoManager,
        private readonly ImageRepositoryInterface $imageRepository,
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
        return $this->successCollectionResponse($videos);
    }

    #[Route('/', name: 'api_admin_videos_create', methods: ['POST'])]
    #[OA\Post(
        description: 'Создает новую видеозапись',
        summary: 'Добавить новое видео',
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/VideoRequest')
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Видео успешно создано',
                content: new OA\JsonContent(ref: '#/components/schemas/Video')
            ),
            new OA\Response(response: 400, description: 'Неверные данные')
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $parsedData = $this->multipartParser->parse($request);
            $video = $this->videoManager->createVideo(
                $parsedData['data'],
                $this->imageRepository
            );

            return $this->successResponse($video, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e);
        }
    }

    #[Route('/{id}', name: 'api_admin_videos_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Получить видео по ID',
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(response: 200, description: 'Данные видео'),
            new OA\Response(response: 422, description: 'Видео не найдено')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $video = $this->getVideoOrFail($id);
        return $this->successResponse($video);
    }

    #[Route('/{id}', name: 'api_admin_videos_update', methods: ['PATCH', 'PUT'])]
    #[OA\Patch(
        summary: 'Обновить видео',
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/VideoRequest')
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(response: 200, description: 'Видео обновлено'),
            new OA\Response(response: 400, description: 'Неверные данные'),
            new OA\Response(response: 422, description: 'Видео не найдено')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $video = $this->getVideoOrFail($id);
        try {
            $parsedData = $this->multipartParser->parse($request);

            $this->videoManager->updateVideo(
                $video,
                $parsedData['data'],
                $this->imageRepository
            );

            return $this->successResponse($video);
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e);
        }
    }

    #[Route('/{id}', name: 'api_admin_videos_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Удалить видео',
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(response: 204, description: 'Видео удалено'),
            new OA\Response(response: 422, description: 'Видео не найдено')
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $video = $this->getVideoOrFail($id);
        try {
            $this->videoManager->deleteVideo($video);
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e);
        }
    }

    private function getVideoOrFail(int $id): Video
    {
        $video = $this->videoRepository->find($id);
        Assert::that($video)->notEmpty('Видео не найдено');
        return $video;
    }

    private function successResponse(Video $video, int $status = 200): JsonResponse
    {
        return $this->json(
            $this->dtoMapper->convertVideoToDto($video),
            $status
        );
    }

    private function successCollectionResponse(array $videos): JsonResponse
    {
        $result = array_map(
            fn ($video) => $this->dtoMapper->convertVideoToDto($video),
            $videos
        );

        return $this->json([
            'status' => 'success',
            'data' => $result,
            'count' => count($result)
        ]);
    }
}
