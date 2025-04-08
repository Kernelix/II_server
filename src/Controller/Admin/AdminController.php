<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

class AdminController extends AbstractController
{
    #[Route('/api/admin', name: 'api_admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Tag(name: 'Admin API', description: 'Метод проверки авторизации')]
    #[OA\Response(
        response: 200,
        description: 'Успешный вход в админ-панель API',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Welcome to Admin API'),
                        new OA\Property(
                            property: '_links',
                            properties: [
                                new OA\Property(property: 'dashboard', type: 'string', example: '/api/admin/dashboard'),
                                new OA\Property(property: 'logout', type: 'string', example: '/api/admin/logout')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Требуется аутентификация',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'error'),
                new OA\Property(property: 'message', type: 'string', example: 'Требуется JWT токен')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Доступ запрещен',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'error'),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Недостаточно прав (требуется роль ADMIN)'
                )
            ]
        )
    )]
    #[OA\Parameter(
        name: 'Authorization',
        description: 'JWT токен в формате "Bearer {token}"',
        in: 'header',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    public function index(): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'data' => [
                'message' => 'Welcome to Admin API',
                '_links' => [
                    'dashboard' => $this->generateUrl('admin_dashboard'),
                    'logout' => $this->generateUrl('api_admin_logout')
                ]
            ]
        ]);
    }
}
