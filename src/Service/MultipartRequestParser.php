<?php

namespace App\Service;

use App\Service\Interface\MultipartParserInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class MultipartRequestParser implements MultipartParserInterface
{
    public function parse(Request $request): array
    {
        $result = [
            'data' => [],
            'file' => null
        ];
        $contentType = $request->headers->get('Content-Type');
        // Если это JSON
        if (str_contains($contentType, 'application/json')) {
            $result['data'] = json_decode($request->getContent(), true) ?? [];
            return $result;
        }

        // Если это multipart/form-data
        $content = $request->getContent();
        $boundary = substr($content, 0, strpos($content, "\r\n"));
        if (!$boundary) {
            $result['data'] = $request->request->all();
            return $result;
        }

        $parts = array_slice(explode($boundary, $content), 1);
        foreach ($parts as $part) {
            if ($part === "--\r\n") {
                break;
            }

            $part = ltrim($part, "\r\n");
            list($rawHeaders, $body) = explode("\r\n\r\n", $part, 2);
            $headers = $this->parseHeaders($rawHeaders);
            if (isset($headers['content-disposition'])) {
                $fieldName = $this->getFieldName($headers['content-disposition']);
                if ($fieldName === 'image' && isset($headers['content-type'])) {
                    $result['file'] = $this->createUploadedFile($headers, $body);
                } elseif ($fieldName) {
                    $result['data'][$fieldName] = substr($body, 0, -2);
                }
            }
        }

        return $result;
    }

    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $header) {
            if (str_contains($header, ':')) {
                list($name, $value) = explode(':', $header, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        return $headers;
    }

    private function getFieldName(string $contentDisposition): ?string
    {
        preg_match('/name="([^"]+)"/', $contentDisposition, $matches);
        return $matches[1] ?? null;
    }

    private function createUploadedFile(array $headers, string $body): UploadedFile
    {
        // Создаем временный файл с уникальным именем
        $tempDir = sys_get_temp_dir();
        $tempName = tempnam($tempDir, 'symfony_upload_');

        if (file_put_contents($tempName, $body) === false) {
            throw new FileException('Could not write to temporary file');
        }



        preg_match('/filename="([^"]+)"/', $headers['content-disposition'], $matches);
        $filename = $matches[1] ?? uniqid();

        return new UploadedFile(
            $tempName,
            $filename,
            $headers['content-type'],
            null,
            true // Перемещаем файл вместо копирования
        );
    }
}
