<?php

namespace App\Controller\Admin;

use App\Dto\Image\ImageOutputDto;
use App\Dto\Video\VideoDto;
use App\Entity\Image;
use App\Entity\Video;
use App\Repository\ImageRepository;
use App\Repository\VideoRepository;
use App\Service\ImageRender;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Filesystem\Filesystem;

#[Route('/api/admin/images')]
#[OA\Tag(name: 'Admin Images', description: 'Управление изображениями в админке')]
class ImageCrudController extends AbstractController
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly VideoRepository $videoRepository
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
        $image = new Image();

        // Обработка загрузки файла
        $uploadedFile = $request->files->get('image');
        if (!$uploadedFile) {
            return $this->json(['error' => 'Image file is required'], 400);
        }

        try {
            $fileName = uniqid() . "."  . $uploadedFile->guessExtension();
            $this->extracted($uploadedFile, $fileName);

            $image->setFilename($fileName)
                ->setDescription($request->get('description'))
                ->setIsFeatured($request->get('isFeatured', false));

            $this->imageRepository->save($image, true);
            $this->imageRepository->clearImageCache([], $image->getId());
            return $this->json($image, Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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

    /**
     * @throws InvalidArgumentException
     */
    /**
     * @throws InvalidArgumentException
     */
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
        $image = $this->imageRepository->find($id);
        if (!$image) {
            return $this->json(['error' => 'Image not found'], Response::HTTP_NOT_FOUND);
        }

        // Инициализация данных
        $data = [];
        $uploadedFile = null;

        // Обработка multipart/form-data для PUT/PATCH
        if (str_contains($request->headers->get('Content-Type'), 'multipart/form-data')) {
            // Парсинг multipart данных вручную
            $content = $request->getContent();
            $boundary = substr($content, 0, strpos($content, "\r\n"));

            if ($boundary) {
                // Разбиваем на части
                $parts = array_slice(explode($boundary, $content), 1);

                foreach ($parts as $part) {
                    if ($part == "--\r\n") {
                        break; // Конец данных
                    }

                    // Получаем headers и content
                    $part = ltrim($part, "\r\n");
                    list($rawHeaders, $body) = explode("\r\n\r\n", $part, 2);

                    // Парсим headers
                    $headers = [];
                    foreach (explode("\r\n", $rawHeaders) as $header) {
                        list($name, $value) = explode(':', $header, 2);
                        $headers[strtolower(trim($name))] = trim($value);
                    }

                    // Обработка файла
                    if (isset($headers['content-disposition'])) {
                        preg_match('/name="([^"]+)"/', $headers['content-disposition'], $matches);
                        $fieldName = $matches[1] ?? null;

                        if ($fieldName === 'image' && isset($headers['content-type'])) {
                            // Создаем временный файл
                            $tempFile = tmpfile();
                            fwrite($tempFile, $body);
                            $tempFilePath = stream_get_meta_data($tempFile)['uri'];

                            $uploadedFile = new UploadedFile(
                                $tempFilePath,
                                $matches['filename'] ?? uniqid(),
                                $headers['content-type'],
                                null,
                                true
                            );
                        } else {
                            // Обычные поля формы
                            $data[$fieldName] = substr($body, 0, -2); // Удаляем \r\n в конце
                        }
                    }
                }
            }
        } else {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            if ($request->files->has('image')) {
                $uploadedFile = $request->files->get('image');
                if ($uploadedFile instanceof UploadedFile) {
                    // Генерация уникального имени файла
                    $fileName = uniqid() . '.' . $uploadedFile->guessExtension();
                    $this->extracted($uploadedFile, $fileName);

                    // Удаление старых версий изображения
                    $this->deleteImageFiles($image->getFilename());

                    $image->setFilename($fileName);
                }
            }

            // 2. Обновление простых полей
            if (isset($data['description'])) {
                $image->setDescription($data['description']);
            }

            if (isset($data['isFeatured'])) {
                $image->setIsFeatured(filter_var($data['isFeatured'], FILTER_VALIDATE_BOOLEAN));
            }

            // 3. Обработка связей
            if (!empty($data['parentId'])) {
                $parent = $this->imageRepository->find($data['parentId']);
                if ($parent) {
                    $image->setParentId($parent);
                }
            }

            // 4. Обработка видео
            if (!empty($data['videos'])) {
                $this->processVideos($image, $data['videos']);
            }

            $this->imageRepository->save($image, true);
            return $this->json($this->entityToDto($image));

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
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

    /**
     * @param mixed $uploadedFile
     * @param string $fileName
     * @return void
     */
    public function extracted(mixed $uploadedFile, string $fileName): void
    {
        $tempDir = $this->getParameter('project_temp_dir');

        $fs = new Filesystem();
        $fs->mkdir($tempDir, 0755);

        $tempPath = $uploadedFile->move($tempDir, $fileName);
        $imageInfo = getimagesize($tempPath);
        $width = $imageInfo[0];

        // Генерация 3 размеров
        $this->generateImageVersions($tempPath, $fileName, $width);
        $fs->remove($tempPath);
    }

    private function processVideos(Image $image, $videosData): void
    {
        if (is_string($videosData)) {
            $videosData = json_decode($videosData, true);
        }

        foreach ($videosData as $videoData) {
            $video = isset($videoData['id'])
                ? $this->videoRepository->find($videoData['id'])
                : new Video();

            $video->setTitle($videoData['title'] ?? '');
            $video->setYoutubeUrl($videoData['youtube_url'] ?? '');
            $video->setImage($image->getParentId() ?: $image);

            $this->videoRepository->save($video, false);
        }
    }
    private function entityToDto(Image $image): ImageOutputDto
    {
        $dto = new ImageOutputDto(
            $image->getId(),
            $image->getFilename(),
            $image->getDescription(),
            $image->isFeatured(),
            $image->getParentId()?->getId()
        );

        // Добавляем связанные видео
        foreach ($image->getVideos() as $video) {
            $dto->videos[] = new VideoDto(
                $video->getId(),
                $video->getTitle(),
                $video->getYoutubeUrl()
            );
        }

        return $dto;
    }
}
