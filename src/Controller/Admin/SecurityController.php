<?php

namespace App\Controller\Admin;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SecurityController extends AbstractController
{
    #[Route('/api/admin/login', name: 'api_admin_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/login',
        description: 'Проверка учетных данных и получение JWT токена',
        summary: 'Аутентификация администратора',
        requestBody: new OA\RequestBody(
            description: 'Учетные данные администратора',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123')
                ],
                type: 'object'
            )
        ),
        tags: ['Admin Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешная аутентификация',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'token',
                            type: 'string',
                            example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
                        ),
                        new OA\Property(property: 'refresh_token', type: 'string', example: 'def50200ae2a8f...')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Неверный запрос',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid request format')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Неверные учетные данные',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials')
                    ]
                )
            ),
            new OA\Response(
                response: 405,
                description: 'Метод не разрешен',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Используйте POST запрос с JSON-телом {email, password}'
                        )
                    ]
                )
            )
        ]
    )]
    public function login(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'Используйте POST запрос с JSON-телом {email, password}'
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    #[Route('/api/admin/logout', name: 'api_admin_logout', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/admin/logout',
        description: 'Инвалидация JWT токена (реализация зависит от стратегии)',
        summary: 'Выход из системы',
        security: [['Bearer' => []]],
        tags: ['Admin Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный выход',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'You have been logged out successfully.'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Требуется аутентификация',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Authentication required')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Доступ запрещен',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    public function logout(): JsonResponse
    {
        // В stateless-приложении логаут реализуется на клиенте
        return $this->json([
            'status' => 'success',
            'message' => 'You have been logged out successfully.'
        ]);
    }
}
