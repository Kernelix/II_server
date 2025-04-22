<?php

namespace App\Controller\Admin;

use App\Entity\Image;
use App\Repository\Interface\ImageRepositoryInterface;
use App\Service\Interface\DtoMapperInterface;
use App\Service\Interface\ErrorHandlerInterface;
use App\Service\Interface\ImageManagerInterface;
use App\Service\Interface\MultipartParserInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/images')]
#[OA\Tag(name: 'Admin Images', description: 'Управление изображениями в админке')]
class ImageCrudController extends AbstractController
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly DtoMapperInterface $dtoMapper,
        private readonly ErrorHandlerInterface $errorService,
        private readonly MultipartParserInterface $multipartParser,
        private readonly ImageManagerInterface $imageManager,
    ) {
    }

    #[Route('/', name: 'api_admin_images_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        description: 'Возвращает все изображения для админ-панели',
        summary: 'Список изображений',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Image')
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $images = $this->imageRepository->findParentImages();
        $result = [];
        foreach ($images as $image) {
            $result[] = $this->dtoMapper->convertImageToDto($image);
        }
        return $this->json([
            'status' => 'success',
            'data' => $result,
            'count' => count($result)
        ]);
    }

    #[Route('/', name: 'api_admin_images_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        description: 'Загрузка нового изображения с автоматической генерацией 3 размеров',
        summary: 'Создание изображения',
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'image', type: 'string', format: 'binary'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'isFeatured', type: 'boolean', nullable: true)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Изображение создано',
                content: new OA\JsonContent(ref: '#/components/schemas/Image')
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации'
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('image');
            if (!$uploadedFile) {
                throw new \InvalidArgumentException('Image file is required');
            }

            $image = $this->imageManager->createImage(
                $uploadedFile,
                $request->get('description'),
                $request->get('isFeatured', false)
            );

            return $this->successResponse($image);
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_admin_images_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        summary: 'Получение изображения',
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ',
                content: new OA\JsonContent(ref: '#/components/schemas/Image')
            ),
            new OA\Response(response: 404, description: 'Не найдено')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $image = $this->getImageOrFail($id);
        return $this->successResponse($image);
    }


    #[Route('/{id}', name: 'api_admin_images_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(
        summary: 'Обновление изображения',
        requestBody: new OA\RequestBody(
            description: 'Данные для обновления изображения (multipart/form-data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'image',
                            description: 'Файл изображения',
                            type: 'string',
                            format: 'binary'
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'isFeatured',
                            type: 'boolean',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'parentId',
                            description: 'ID родительского изображения',
                            type: 'integer',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'videos',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'youtube_url', type: 'string')
                                ],
                                type: 'object'
                            ),
                            nullable: true
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Изображение обновлено',
                content: new OA\JsonContent(ref: '#/components/schemas/Image')
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
            new OA\Response(response: 404, description: 'Не найдено')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $image = $this->getImageOrFail($id);
            $parsedData = $this->multipartParser->parse($request);

            $this->imageManager->updateImage(
                $image,
                $parsedData['data'],
                $parsedData['file']
            );

            return $this->successResponse($image);
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e);
        }
    }


    #[Route('/{id}', name: 'api_admin_images_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(
        summary: 'Удаление изображения',
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(response: 204, description: 'Удалено'),
            new OA\Response(response: 404, description: 'Не найдено')
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        try {
            $image = $this->getImageOrFail($id);
            $this->imageManager->deleteImage($image);
            return new JsonResponse(status: Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e);
        }
    }


    #[Route('/{id}/toggle-publish', name: 'api_admin_images_toggle_publish', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        summary: 'Переключение статуса публикации',
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Статус обновлен',
                content: new OA\JsonContent(ref: '#/components/schemas/Image')
            ),
            new OA\Response(response: 404, description: 'Не найдено')
        ]
    )]
    public function togglePublish(int $id): JsonResponse
    {
        $image = $this->getImageOrFail($id);
        $this->imageManager->togglePublish($image);

        return $this->successResponse($image);
    }



    #[Route('/{id}/metadata', name: 'api_admin_images_update_metadata', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateMetadata(Request $request, int $id): JsonResponse
    {
        try {
            $image = $this->getImageOrFail($id);
            $parsedData = $this->multipartParser->parse($request);
            $data = $parsedData['data'];

            $this->imageManager->updateMetadata(
                $image,
                $data['description'] ?? null,
                $data['isFeatured'] ?? true,
                $data['isPublished'] ?? null
            );

            return $this->successResponse($image);
        } catch (\Exception $e) {
            return $this->errorService->jsonError($e);
        }
    }

    private function getImageOrFail(int $id): Image
    {
        $image = $this->imageRepository->find($id);
        if (!$image) {
            throw new NotFoundHttpException('Image not found');
        }
        return $image;
    }

    private function successResponse(Image $image, int $status = 200): JsonResponse
    {
        return $this->json(
            $this->dtoMapper->convertImageToDto($image),
            $status
        );
    }
}
