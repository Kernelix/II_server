<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GalleryItemNotFoundException extends NotFoundHttpException
{
    public function __construct(string $message = 'Изображение не найдено')
    {
        parent::__construct($message);
    }
}