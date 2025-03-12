<?php
namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        // Если пользователь не авторизован, перенаправляем его на страницу входа
        if (!$this->getUser()) {
            return $this->redirectToRoute('admin_login');
        }

        // Если пользователь авторизован, перенаправляем его на главную страницу админки
        return $this->redirectToRoute('admin_dashboard');
    }
}