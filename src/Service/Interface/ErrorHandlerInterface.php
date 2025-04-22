<?php

namespace App\Service\Interface;

use Symfony\Component\HttpFoundation\JsonResponse;

interface ErrorHandlerInterface
{
    /**
     * Создает JSON-ответ с ошибкой
     *
     * @param string $message Сообщение об ошибке
     * @param int $status HTTP-статус код (по умолчанию 400)
     */
    public function jsonError(string $message, int $status = 400): JsonResponse;
}
