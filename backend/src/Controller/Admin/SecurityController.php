<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/api/admin/login', name: 'api_admin_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'Используйте POST запрос с JSON-телом {email, password}'
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }


    #[Route('/api/admin/logout', name: 'api_admin_logout', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function logout(): JsonResponse
    {
        // В stateless-приложении логаут реализуется на клиенте
        return $this->json([
            'status' => 'success',
            'message' => 'You have been logged out successfully.'
        ]);
    }
}
