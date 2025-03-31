<?php

namespace App\Controller\Admin;

use App\Entity\Image;
use App\Form\ImageType;
use App\Repository\ImageRepository;
use App\Service\ImageRender;
use Assert\Assert;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/image')]
class ImageCrudController extends AbstractController
{
    #[Route('/', name: 'admin_image_index', methods: ['GET'])]
    public function index(ImageRepository $imageRepository): Response
    {
        return $this->render('admin/image/index.html.twig', [
            'images' => $imageRepository->findParentImages(),
        ]);
    }

    #[Route('/new', name: 'admin_image_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('admin_login'); // Перенаправление на страницу входа
        }
        $image = new Image();
        $noNeeds = false;
        $form = $this->createForm(ImageType::class, $image, [
            'include_parent_field' => $noNeeds,
            'include_description_field' => $noNeeds,
            'include_videos_field' => $noNeeds,
            'isPublished' => $noNeeds,
            'isFeatured' => $noNeeds
        ]);
        // Отключаем кэширование
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            // Обработка загрузки файла
            $file = $form->get('filename')->getData();
            if ($file) {
                $fileName = uniqid() . '.' . $file->guessExtension();
                $tempDir = $this->getParameter('project_temp_dir');

                // Создаем директорию при необходимости
                $fs = new Filesystem();
                if (!$fs->exists($tempDir)) {
                    $fs->mkdir($tempDir, 0755);
                }

                $tempPath = $file->move($tempDir, $fileName);
                // Получаем размеры изображения
                $imageInfo = getimagesize($tempPath);
                $width = $imageInfo[0]; // Ширина

                $fs = new Filesystem();

                $destSpath = $this->getParameter('images_s_dir');
                $destMpath = $this->getParameter('images_m_dir');
                $destLpath = $this->getParameter('images_l_dir');

                if ($width >= 300){
                    ImageRender::resize($tempPath, $destSpath . $fileName, ['webp', 90], 300, null, 'scale');
                }else{
                    $fs->copy($tempPath, $destSpath . $fileName, false);
                }
                if ($width >= 1920){
                    ImageRender::resize($tempPath, $destMpath . $fileName, ['webp', 90], 1920, null, 'scale');
                }else{
                    $fs->copy($tempPath, $destMpath . $fileName, false);
                }
                if ($width >= 5760){
                    ImageRender::resize($tempPath, $destLpath . $fileName, ['jpeg', 60], 5760, null, 'scale');
                }else{
                    $fs->copy($tempPath, $destLpath . $fileName, false);
                }

                $fs->remove($tempPath);
                $image->setFilename($fileName);
            }


            $entityManager->persist($image);
            $entityManager->flush();

            return $this->redirectToRoute('admin_image_index');
        }

        return $this->render('admin/image/new.html.twig', [
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_image_show', methods: ['GET'])]
    public function show(int $id, ImageRepository $imageRepository): Response
    {
        $image = $imageRepository->find($id);
        Assert::that($image)->notEmpty('Изображение не найдено');
        return $this->render('admin/image/show.html.twig', [
            'image' => $image,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_image_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, ImageRepository $imageRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('admin_login'); // Перенаправление на страницу входа
        }
        $image = $imageRepository->find($id);
        Assert::that($image)->notEmpty('Изображение не найдено');
        $form = $this->createForm(ImageType::class, $image);

        // Отключаем кэширование
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        $form->handleRequest($request);


        $destSpath = $this->getParameter('images_s_dir');
        $destMpath = $this->getParameter('images_m_dir');
        $destLpath = $this->getParameter('images_l_dir');

        if ($form->isSubmitted() && $form->isValid()) {
            // Обработка загрузки файла, если он был изменён
            $file = $form->get('filename')->getData();
            if ($file) {
                // Удаляем старое изображение
                $oldFilename = $image->getFilename();
                if ($oldFilename) {
                    $oldFilePathS = $destSpath . $oldFilename;
                    $oldFilePathM = $destMpath . $oldFilename;
                    $oldFilePathL = $destLpath . $oldFilename;
                    if (file_exists($oldFilePathS)) {
                        unlink($oldFilePathS);
                    }
                    if (file_exists($oldFilePathM)) {
                        unlink($oldFilePathM);
                    }
                    if (file_exists($oldFilePathL)) {
                        unlink($oldFilePathL);
                    }
                }

                $tempDir = $this->getParameter('project_temp_dir');
                // Сохраняем новое изображение
                $fileName = uniqid() . '.' . $file->guessExtension();

                // Создаем директорию при необходимости
                $fs = new Filesystem();
                if (!$fs->exists($tempDir)) {
                    $fs->mkdir($tempDir, 0755);
                }

                $tempPath = $file->move($tempDir, $fileName);
                // Получаем размеры изображения
                $imageInfo = getimagesize($tempPath);
                $width = $imageInfo[0]; // Ширина

                $fs = new Filesystem();


                if ($width >= 300) {
                    ImageRender::resize($tempPath, $destSpath . $fileName, ['webp', 90], 300, null, 'scale');
                } else {
                    $fs->copy($tempPath, $destSpath . $fileName, false);
                }
                if ($width >= 1920) {
                    ImageRender::resize($tempPath, $destMpath . $fileName, ['webp', 90], 1920, null, 'scale');
                } else {
                    $fs->copy($tempPath, $destMpath . $fileName, false);
                }
                if ($width >= 5760) {
                    ImageRender::resize($tempPath, $destLpath . $fileName, ['jpeg', 60], 5760, null, 'scale');
                } else {
                    $fs->copy($tempPath, $destLpath . $fileName, false);
                }

                $fs->remove($tempPath);
                $image->setFilename($fileName);
            }

            // Установка parentId, если он был выбран
            $parentImage = $form->get('parentId')->getData();
            if ($parentImage) {
                $image->setParentId($parentImage);
            }

            // Закрепление видео
            $videos = $form->get('videos')->getData();
            foreach ($videos as $video) {
                // Если выбрана родительская картинка, привязываем видео к ней
                if ($parentImage) {
                    $video->setImage($parentImage);
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('admin_image_index');
        }

        return $this->render('admin/image/edit.html.twig', [
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_image_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ImageRepository $imageRepository, EntityManagerInterface $entityManager): Response
    {
        $image = $imageRepository->find($id);
        Assert::that($image)->notEmpty('Изображение не найдено');

        if ($this->isCsrfTokenValid('delete' . $image->getId(), $request->request->get('_token'))) {
            $destSpath = $this->getParameter('images_s_dir');
            $destMpath = $this->getParameter('images_m_dir');
            $destLpath = $this->getParameter('images_l_dir');


            // Удаляем старое изображение
            $filename = $image->getFilename();
            if ($filename) {
                $filePathS = $destSpath . $filename;
                $filePathM = $destMpath . $filename;
                $filePathL = $destLpath . $filename;
                if (file_exists($filePathS)) {
                    unlink($filePathS);
                }
                if (file_exists($filePathM)) {
                    unlink($filePathM);
                }
                if (file_exists($filePathL)) {
                    unlink($filePathL);
                }
            }

            $entityManager->remove($image);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_image_index');
    }

    #[Route('/{id}/toggle-publish', name: 'admin_image_toggle_publish', methods: ['POST'])]
    public function togglePublish(int $id, ImageRepository $imageRepository, EntityManagerInterface $entityManager): Response
    {
        $image = $imageRepository->find($id);
        Assert::that($image)->notEmpty('Изображение не найдено');
        $image->setIsPublished(!$image->isPublished());
        $entityManager->flush();

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/image/add', name: 'admin_image_add', methods: ['POST'])]
    public function addImage(Request $request, EntityManagerInterface $entityManager, ImageRepository $imageRepository): JsonResponse
    {
        // Получаем данные из формы
        $description = $request->request->get('description');
        $filename = $request->request->get('filename');
        $isFeatured = $request->request->get('isFeatured', false); // По умолчанию false


        $id = $request->request->get('id');
        $image = $imageRepository->find($id);
        Assert::that($image)->notEmpty('Изображение не найдено');


            try {
                $image->setDescription($description);
                $image->setFilename($filename);
                $image->setIsFeatured((bool)$isFeatured); // Устанавливаем isFeatured
                $image->setIsPublished(false); // По умолчанию не опубликовано

                // Сохраняем в базе данных
                $entityManager->persist($image);
                $entityManager->flush();

                return new JsonResponse(['success' => true, 'message' => 'Пост сохранен!']);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
    }



    #[Route('/admin/gallery/partial', name: 'admin_gallery_partial', methods: ['GET'])]
    public function galleryPartial(ImageRepository $imageRepository): Response
    {
        return $this->render('admin/image/_gallery.html.twig', [
            'images' => $imageRepository->findParentImages(),
        ]);
    }
}
