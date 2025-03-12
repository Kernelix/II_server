<?php
namespace App\Controller\Admin;

use App\Repository\ImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(ImageRepository $imageRepository): Response
    {
        // Получаем все изображения с их видео
        $images = $imageRepository->findAllWithVideosAndParent();
        // Проверяем, что пользователь авторизован
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/dashboard/index.html.twig', [
            'images' => $images,
        ]);
    }
}