<?php

namespace App\Service;

use App\Service\Interface\ImageStorageInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageFileManager implements ImageStorageInterface
{
    private string $sDir;
    private string $mDir;
    private string $lDir;
    private Filesystem $fs;

    public function __construct(
        string $sDir,
        string $mDir,
        string $lDir,
        Filesystem $fs
    ) {
        $this->sDir = $sDir;
        $this->mDir = $mDir;
        $this->lDir = $lDir;
        $this->fs = $fs;
    }

    public function upload(UploadedFile $file): string
    {
        $fileName = uniqid() . '.' . $file->guessExtension();
        $tempDir = $_ENV['TEMP_DIR'] ?? sys_get_temp_dir() . '/';

        $this->fs->mkdir($tempDir);
        $tempPath = $file->move($tempDir, $fileName);

        $imageInfo = getimagesize($tempPath);
        $this->generateVersions($tempPath, $fileName, $imageInfo[0]);

        $this->fs->remove($tempPath);

        return $fileName;
    }

    public function delete(string $filename): void
    {
        $paths = [
            $this->sDir . $filename,
            $this->mDir . $filename,
            $this->lDir . $filename
        ];

        foreach ($paths as $path) {
            if ($this->fs->exists($path)) {
                $this->fs->remove($path);
            }
        }
    }

    private function generateVersions(string $tempPath, string $fileName, int $originalWidth): void
    {
        if ($originalWidth >= 300) {
            ImageRender::resize($tempPath, $this->sDir . $fileName, ['webp', 90], 300);
        } else {
            $this->fs->copy($tempPath, $this->sDir . $fileName);
        }

        if ($originalWidth >= 1920) {
            ImageRender::resize($tempPath, $this->mDir . $fileName, ['webp', 90], 1920);
        } else {
            $this->fs->copy($tempPath, $this->mDir . $fileName);
        }

        if ($originalWidth >= 5760) {
            ImageRender::resize($tempPath, $this->lDir . $fileName, ['jpeg', 60], 5760);
        } else {
            $this->fs->copy($tempPath, $this->lDir . $fileName);
        }
    }
}
