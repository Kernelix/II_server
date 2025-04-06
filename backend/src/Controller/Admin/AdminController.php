<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    #[Route('/api/admin', name: 'api_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'data' => [
                'message' => 'Welcome to Admin API',
                '_links' => [
                    'dashboard' => $this->generateUrl('api_admin_dashboard'),
                    'logout' => $this->generateUrl('api_admin_logout')
                ]
            ]
        ]);
    }
}
