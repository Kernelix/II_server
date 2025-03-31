<?php

namespace App\Service;

class ImageRender
{
    /**
     * Создает ресурс изображения из файла.
     *
     * @param string $filepath Путь к файлу изображения
     *
     * @return \GdImage|false Возвращает ресурс изображения или false в случае ошибки
     */
    private static function imageCreateFromAny(string $filepath): \GdImage|false
    {
        $im = null;
        $type = exif_imagetype($filepath); // [] if you don't have exif you could use getImageSize()
        $allowedTypes = [
            1,  // [] gif
            2,  // [] jpg
            3,  // [] png
            6,  // [] bmp
            18, // [] webp
        ];
        if (!in_array($type, $allowedTypes)) {
            return false;
        }

        switch ($type) {
            case 1:
                $im = imagecreatefromgif($filepath);
                break;
            case 2:
                $im = imagecreatefromjpeg($filepath);
                break;
            case 3:
                $im = imagecreatefrompng($filepath);
                break;
            case 6:
                $im = imagecreatefrombmp($filepath);
                break;
            case 18:
                $im = imagecreatefromwebp($filepath);
        }

        return $im;
    }

    /**
     * Проверяет, соответствует ли изображение минимальным размерам
     *
     * @param string                $filepath Путь к файлу изображения
     * @param array{0: int, 1: int} $size     Минимальные размеры [ширина, высота]
     *
     * @return bool|null Возвращает:
     *                   - true если изображение МЕНЬШЕ указанных размеров по любой из сторон
     *                   - false если изображение БОЛЬШЕ или РАВНО минимальным размерам по обеим сторонам
     *                   - null если не удалось прочитать изображение
     */
    public static function checkSize(string $filepath, array $size): ?bool
    {
        // Получаем изображение
        $img = @self::imageCreateFromAny($filepath);
        if (!is_resource($img) && !$img instanceof \GdImage) {
            return null;
        }

        // Получаем размеры
        $width = imagesx($img);
        $height = imagesy($img);

        // Освобождаем память
        imagedestroy($img);

        return ((int) $width < $size[0]) || ((int) $height < $size[1]);
    }

    /**
     * Изменяет размер изображения согласно заданным параметрам
     *
     * @param array{0: string, 1: int} $setting Настройки обработки:
     *                                          - [0]: тип изображения ('webp', 'jpeg', 'png')
     *                                          - [1]: качество (0-100)
     *
     * @return string|false Путь к обработанному файлу или false при ошибке
     */
    public static function resize(
        string $sourcefilepath,
        string $destdir,
        array $setting,
        int $reqwidth,
        ?int $reqheight = null,
        string|false $type = false,
    ): string|false {
        $thumbnail_path = $destdir;
        $image_file = $sourcefilepath;

        // Проверка поддержки WebP
        if ('webp' == $setting[0] && !function_exists('imagewebp')) {
            throw new \RuntimeException('WebP support is not available on this server');
        }

        $img = self::imageCreateFromAny($image_file);
        if (!$img) {
            throw new \RuntimeException('Could not create image from file');
        }

        $width = imagesx($img);
        $height = imagesy($img);

        // Автоматический расчет высоты при сохранении пропорций
        if (null === $reqheight) {
            $reqheight = (int) ($height * ($reqwidth / $width));
        }

        // Принудительное приведение размеров
        $reqwidth = (int) round($reqwidth);
        $reqheight = null == $reqheight ? null : (int) round($reqheight);

        //        dd($orient, $width * $orient);

        $original_aspect = $width / $height;
        $thumb_aspect = $reqwidth / $reqheight;

        if ('crop' == $type) {
            if ($original_aspect >= $thumb_aspect) {
                $new_height = $reqheight;
                $new_width = $width / ($height / $reqheight);
            } else {
                $new_width = $reqwidth;
                $new_height = $height / ($width / $reqwidth);
            }
            $rest_width = $new_width - $reqwidth;
            $rest_height = $new_height - $reqheight;

            $tmp_img = imagecreatetruecolor($reqwidth, $reqheight);
        } elseif ('scale' == $type) {
            $ratio = min($reqwidth / $width, ($reqheight ?: PHP_FLOAT_MAX) / $height);

            $new_width = (int) round($width * $ratio);
            $new_height = (int) round($height * $ratio);

            $rest_width = 0;
            $rest_height = 0;

            // Используем imagescale для точного размера
            $tmp_img = imagescale($img, $reqwidth, $reqheight ?: -1);
        } else {
            $new_width = $reqwidth;
            $new_height = $reqheight;

            $rest_width = 0;
            $rest_height = 0;

            $tmp_img = imagecreatetruecolor($new_width, $new_height);
        }

        imagecopyresampled($tmp_img, $img,
            -($rest_width / 2),
            -($rest_height / 2),
            0,
            0,
            $new_width,
            $new_height,
            $width,
            $height
        );
        // Сохранение в нужном формате
        $result = false;
        switch ($setting[0]) {
            case 'webp':
                // Особые настройки для WebP
                imagepalettetotruecolor($tmp_img);
                imagealphablending($tmp_img, true);
                imagesavealpha($tmp_img, true);
                $result = imagewebp($tmp_img, $destdir, $setting[1]);
                break;

            case 'jpeg':
                $result = imagejpeg($tmp_img, $destdir, $setting[1]);
                break;

            case 'png':
                $result = imagepng($tmp_img, $destdir, $setting[1]);
                break;

            default:
                $result = imagejpeg($tmp_img, $destdir, 70);
        }

        imagedestroy($img);
        imagedestroy($tmp_img);

        return $result ? $destdir : false;
    }
}
