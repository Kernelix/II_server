<?php

namespace App\Controller\Admin;

use App\Dto\Gallery\GalleryImageDto;
use App\Dto\Image\ImageOutputDto;
use App\Dto\Video\VideoDto;
use App\Entity\Image;
use App\Entity\Video;
use App\Repository\ImageRepository;
use App\Repository\VideoRepository;
use App\Service\ImageRender;
use App\Service\MultipartRequestParser;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly VideoRepository $videoRepository,
        private readonly MultipartRequestParser $multipartParser
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
            $this->imageRepository->clearGalleryCache($image->getId());
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

        $parsedData = $this->multipartParser->parse($request);

        try {
            if ($parsedData['file']) {
                $fileName = uniqid() . '.' . $parsedData['file']->guessExtension();
                $this->extracted($parsedData['file'], $fileName);
                $this->deleteImageFiles($image->getFilename());
                $image->setFilename($fileName);
            }

            $data = $parsedData['data'];
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
            $this->imageRepository->clearGalleryCache($id);
            return $this->json($this->entityToDto($image));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (InvalidArgumentException $e) {
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
        $this->imageRepository->clearGalleryCache($id);

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

        if (!$image->getVideos()->isEmpty()) {
            foreach ($image->getVideos() as $video) {
                $videoDto = new VideoDto();
                $videoDto->id = $video->getId();
                $videoDto->title = $video->getTitle();
                $videoDto->youtubeUrl = $video->getYoutubeUrl();
                $videoDto->imageId = $video->getImage()?->getId();

                // Если нужно добавить изображение, используем существующий GalleryImageDto
                if ($video->getImage()) {
                    $videoDto->image = $this->convertToGalleryImageDto($video->getImage());
                }

                $dto->videos[] = $videoDto;
            }
        }

        return $dto;
    }

    #[Route('/{id}/metadata', name: 'api_admin_images_update_metadata', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateMetadata(Request $request, int $id): JsonResponse
    {
        $image = $this->imageRepository->find($id);
        if (!$image) {
            return $this->json(['error' => 'Image not found'], Response::HTTP_NOT_FOUND);
        }

        $parsedData = $this->multipartParser->parse($request);
        $data = $parsedData['data'];

        try {
            // Обновление полей
            if (array_key_exists('description', $data)) {
                $image->setDescription($data['description']);
            }


            $image->setIsFeatured(true);


            if (array_key_exists('isPublished', $data)) {
                $image->setIsPublished(filter_var($data['isPublished'], FILTER_VALIDATE_BOOLEAN));
            }

            $this->imageRepository->save($image, true);
            $this->imageRepository->clearGalleryCache($image->getId());

            return $this->json($this->entityToDto($image));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
    private function convertToGalleryImageDto(Image $image): GalleryImageDto
    {
        $dto = new GalleryImageDto();
        $dto->id = $image->getId();
        $dto->filename = $image->getFilename();
        $dto->description = $image->getDescription();
        $dto->links = [
            'self' => $this->generateUrl('api_admin_images_show', ['id' => $image->getId()])
        ];

        return $dto;
    }
}
