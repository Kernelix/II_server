<?php

namespace App\Service;

use App\Service\Interface\CachePurgerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class CachePurgerService implements CachePurgerInterface
{
    private string $cachePath;
    private Filesystem $filesystem;

    public function __construct(string $nginxCachePath, Filesystem $filesystem)
    {
        $this->cachePath = $nginxCachePath;
        $this->filesystem = $filesystem;
    }

    public function purgeAll(): void
    {
        $cachePath = rtrim($this->cachePath, '/') . '/';

        if (!$this->filesystem->exists($cachePath)) {
            throw new \RuntimeException("Директория {$cachePath} не существует");
        }

        $finder = new Finder();
        $finder->in($cachePath)->depth(0);

        foreach ($finder as $file) {
            try {
                $this->filesystem->remove($file->getRealPath());
            } catch (IOExceptionInterface $e) {
                // Логирование ошибки без прерывания процесса
                error_log($e->getMessage());
            }
        }
    }
}
