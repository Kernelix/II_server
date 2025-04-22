<?php

namespace App\Service;

use App\Service\Interface\ErrorHandlerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorService implements ErrorHandlerInterface
{
    public function jsonError(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(
            ['error' => $message],
            $status
        );
    }
}
