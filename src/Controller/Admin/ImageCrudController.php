<?php

namespace App\Controller\Admin;

use App\Entity\Image;
use App\Form\ImageType;
use App\Repository\ImageRepository;
use App\Service\ImageProcessor;
use Assert\Assert;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function new(Request $request, EntityManagerInterface $entityManager, ImageProcessor $imageProcessor): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('admin_login'); // Перенаправление на страницу входа
        }
        $image = new Image();
        $form = $this->createForm(ImageType::class, $image);
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
                $file->move($this->getParameter('images_directory'), $fileName);
                $image->setFilename($fileName);

                // Создание миниатюры
                $sourcePath = $this->getParameter('images_directory') . '/' . $fileName;
                $thumbnailPath = $this->getParameter('images_directory') . '/thumbs/' . $fileName;
                $imageProcessor->createThumbnail($sourcePath, $thumbnailPath, 150, 150); // Размер миниатюры 150x150
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
    public function edit(Request $request, int $id, ImageRepository $imageRepository, EntityManagerInterface $entityManager, ImageProcessor $imageProcessor): Response
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



        if ($form->isSubmitted() && $form->isValid()) {
            // Обработка загрузки файла, если он был изменён
            $file = $form->get('filename')->getData();
            if ($file) {
                // Удаляем старое изображение
                $oldFilename = $image->getFilename();
                if ($oldFilename) {
                    $oldFilePath = $this->getParameter('images_directory') . '/' . $oldFilename;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                    // Удаляем миниатюру
                    $oldThumbPath = $this->getParameter('images_directory') . '/thumbs/' . $oldFilename;
                    if (file_exists($oldThumbPath)) {
                        unlink($oldThumbPath);
                    }
                }

                // Сохраняем новое изображение
                $fileName = uniqid() . '.' . $file->guessExtension();
                $file->move($this->getParameter('images_directory'), $fileName);
                $image->setFilename($fileName);

                // Создаем миниатюру
                $sourcePath = $this->getParameter('images_directory') . '/' . $fileName;
                $thumbnailPath = $this->getParameter('images_directory') . '/thumbs/' . $fileName;
                $imageProcessor->createThumbnail($sourcePath, $thumbnailPath, 150, 150); // Размер миниатюры 150x150
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
            // Удаление файла изображения
            $filePath = $this->getParameter('images_directory') . '/' . $image->getFilename();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // Удаление миниатюры
            $thumbnailPath = $this->getParameter('images_directory') . '/thumbs/' . $image->getFilename();
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
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

        return $this->redirectToRoute('admin_image_index');
    }
}
