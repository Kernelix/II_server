<?php

namespace App\Controller\Admin;

use App\Entity\Video;
use App\Form\VideoType;
use App\Repository\VideoRepository;
use Assert\Assert;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/video')]
class VideoCrudController extends AbstractController
{
    #[Route('/', name: 'admin_video_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        return $this->render('admin/video/index.html.twig', [
            'videos' => $videoRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_video_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('admin_login'); // Перенаправление на страницу входа
        }
        $video = new Video();
        $form = $this->createForm(VideoType::class, $video);
        // Отключаем кэширование
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Получаем ссылку на YouTube из формы
            $youtubeUrl = $video->getYoutubeUrl();

            // Извлекаем ID видео
            if ($youtubeUrl) {
                if (preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $youtubeUrl, $matches)) {
                    // Для стандартных ссылок
                    $videoId = $matches[1];
                } elseif (preg_match('/youtu\.be\/([^\\?\\&]+)/', $youtubeUrl, $matches)) {
                    // Для коротких ссылок
                    $videoId = $matches[1];
                }

                if (isset($videoId)) {
                    // Формируем embed-ссылку
                    $embedUrl = 'https://www.youtube.com/embed/'.$videoId;
                    // Сохраняем embed-ссылку в сущность
                    $video->setYoutubeUrl($embedUrl);
                }
            }
            $entityManager->persist($video);
            $entityManager->flush();

            return $this->redirectToRoute('admin_video_index');
        }

        return $this->render('admin/video/new.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_video_show', methods: ['GET'])]
    public function show(int $id, VideoRepository $videoRepository): Response
    {
        $video = $videoRepository->find($id);
        Assert::that($video)->notEmpty('Видео не найдено');

        return $this->render('admin/video/show.html.twig', [
            'video' => $video,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_video_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, VideoRepository $videoRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('admin_login'); // Перенаправление на страницу входа
        }
        $video = $videoRepository->find($id);
        Assert::that($video)->notEmpty('Видео не найдено');
        $form = $this->createForm(VideoType::class, $video);
        // Отключаем кэширование
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Получаем ссылку на YouTube из формы
            $youtubeUrl = $video->getYoutubeUrl();

            // Извлекаем ID видео
            if ($youtubeUrl) {
                if (preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $youtubeUrl, $matches)) {
                    // Для стандартных ссылок
                    $videoId = $matches[1];
                } elseif (preg_match('/youtu\.be\/([^\\?\\&]+)/', $youtubeUrl, $matches)) {
                    // Для коротких ссылок
                    $videoId = $matches[1];
                }

                if (isset($videoId)) {
                    // Формируем embed-ссылку
                    $embedUrl = 'https://www.youtube.com/embed/'.$videoId;
                    // Сохраняем embed-ссылку в сущность
                    $video->setYoutubeUrl($embedUrl);
                }
            }

            // Сохраняем изменения в базе данных
            $entityManager->flush();

            return $this->redirectToRoute('admin_video_index');
        }

        return $this->render('admin/video/edit.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_video_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, VideoRepository $videoRepository, EntityManagerInterface $entityManager): Response
    {
        $video = $videoRepository->find($id);
        Assert::that($video)->notEmpty('Видео не найдено');
        if ($this->isCsrfTokenValid('delete'.$video->getId(), $request->request->get('_token'))) {
            $entityManager->remove($video);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_video_index');
    }
}
