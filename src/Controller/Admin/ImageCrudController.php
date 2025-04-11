<?php

namespace App\Controller\Admin;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\ImageRender;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Filesystem\Filesystem;

#[Route('/api/admin/images')]
#[OA\Tag(name: 'Admin Images', description: 'Управление изображениями в админке')]
class ImageCrudController extends AbstractController
{
    public function __construct(
        private readonly ImageRepository $imageRepository
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

        return $this->json([
            'status' => 'success',
            'data' => $images,
            'count' => count($images)
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
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'isFeatured', type: 'boolean')
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
        $image = new Image();

        // Обработка загрузки файла
        $uploadedFile = $request->files->get('image');
        if (!$uploadedFile) {
            return $this->json(['error' => 'Image file is required'], 400);
        }

        try {
            $fileName = uniqid() . 'Admin.' . $uploadedFile->guessExtension();
            $tempDir = $this->getParameter('project_temp_dir');

            $fs = new Filesystem();
            $fs->mkdir($tempDir, 0755);

            $tempPath = $uploadedFile->move($tempDir, $fileName);
            $imageInfo = getimagesize($tempPath);
            $width = $imageInfo[0];

            // Генерация 3 размеров
            $this->generateImageVersions($tempPath, $fileName, $width);
            $fs->remove($tempPath);

            $image->setFilename($fileName)
                ->setDescription($request->get('description'))
                ->setIsFeatured($request->get('isFeatured', false));

            $this->imageRepository->save($image, true);
            $this->imageRepository->clearImageCache([], $image->getId());

            return $this->json($image, 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
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
        $image = $this->imageRepository->find($id);
        if (!$image) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        return $this->json($image);
    }

    #[Route('/{id}', name: 'api_admin_images_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(
        summary: 'Обновление изображения',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'isFeatured', type: 'boolean')
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Изображение обновлено',
                content: new OA\JsonContent(ref: '#/components/schemas/Image')
            ),
            new OA\Response(response: 404, description: 'Не найдено')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $image = $this->imageRepository->find($id);
        if (!$image) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $image->setDescription($data['description'] ?? $image->getDescription())
            ->setIsFeatured($data['isFeatured'] ?? $image->isFeatured());

        $this->imageRepository->save($image, true);
        $this->imageRepository->clearImageCache([], $id);

        return $this->json($image);
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
        $image = $this->imageRepository->find($id);
        if (!$image) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $this->deleteImageFiles($image->getFilename());
        $this->imageRepository->remove($image, true);
        $this->imageRepository->clearImageCache([], $id);

        return $this->json(null, 204);
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
        $image = $this->imageRepository->find($id);
        if (!$image) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $image->setIsPublished(!$image->isPublished());
        $this->imageRepository->save($image, true);

        return $this->json($image);
    }

    private function generateImageVersions(string $tempPath, string $fileName, int $originalWidth): void
    {
        $fs = new Filesystem();
        $destSpath = $this->getParameter('images_s_dir');
        $destMpath = $this->getParameter('images_m_dir');
        $destLpath = $this->getParameter('images_l_dir');

        if ($originalWidth >= 300) {
            ImageRender::resize($tempPath, $destSpath . $fileName, ['webp', 90], 300, null, 'scale');
        } else {
            $fs->copy($tempPath, $destSpath . $fileName, false);
        }

        if ($originalWidth >= 1920) {
            ImageRender::resize($tempPath, $destMpath . $fileName, ['webp', 90], 1920, null, 'scale');
        } else {
            $fs->copy($tempPath, $destMpath . $fileName, false);
        }

        if ($originalWidth >= 5760) {
            ImageRender::resize($tempPath, $destLpath . $fileName, ['jpeg', 60], 5760, null, 'scale');
        } else {
            $fs->copy($tempPath, $destLpath . $fileName, false);
        }
    }

    private function deleteImageFiles(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        $fs = new Filesystem();
        $paths = [
            $this->getParameter('images_s_dir') . $filename,
            $this->getParameter('images_m_dir') . $filename,
            $this->getParameter('images_l_dir') . $filename
        ];

        foreach ($paths as $path) {
            if ($fs->exists($path)) {
                $fs->remove($path);
            }
        }
    }
}
