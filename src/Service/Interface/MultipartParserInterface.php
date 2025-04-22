<?php

namespace App\Service\Interface;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

interface MultipartParserInterface
{
    /**
     * Парсит входящий запрос с multipart/form-data или JSON контентом
     *
     * @return array{
     *     data: array,
     *     file: UploadedFile|null
     * }
     */
    public function parse(Request $request): array;
}
