<?php

namespace App\Service\Interface;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImageStorageInterface
{
    /** @throws \RuntimeException */
    public function upload(UploadedFile $file): string;

    public function delete(string $filename): void;
}
