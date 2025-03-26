<?php

namespace App\Service;

class ImageRender
{

    private static function imageCreateFromAny($filepath)
    {
        $type = exif_imagetype($filepath); // [] if you don't have exif you could use getImageSize()
        $allowedTypes = array(
            1,  // [] gif
            2,  // [] jpg
            3,  // [] png
            6,  // [] bmp
            18, // [] webp
        );
        if (!in_array($type, $allowedTypes)) {
            return false;
        }

        switch ($type) {
            case 1 :
                $im = imageCreateFromGif($filepath);
                break;
            case 2 :
                $im = imageCreateFromJpeg($filepath);
                break;
            case 3 :
                $im = imageCreateFromPng($filepath);
                break;
            case 6 :
                $im = imageCreateFromBmp($filepath);
                break;
            case 18 :
                $im = imagecreatefromwebp($filepath);
        }
        return $im;
    }

    public static function checkSize($filepath, $size)
    {
        $img = self::imageCreateFromAny($filepath);
        $width = imagesx($img);
        $height = imagesy($img);
//        dd($width, $height);
        if ($width >= $size[0]) return;
        if ($height >= $size[1]) return;
        return true;
    }

    public static function resize($sourcefilepath, $destdir, $setting, $reqwidth, $reqheight = null, $type = false)
    {
        $thumbnail_path = $destdir;
        $image_file = $sourcefilepath;

        // Проверка поддержки WebP
        if ($setting[0] == 'webp' && !function_exists('imagewebp')) {
            throw new \RuntimeException('WebP support is not available on this server');
        }


        $img = self::imageCreateFromAny($image_file);
        if (!$img) {
            throw new \RuntimeException('Could not create image from file');
        }

        $width = imagesx($img);
        $height = imagesy($img);


        // Автоматический расчет высоты при сохранении пропорций
        if ($reqheight === null) {
            $reqheight = (int)($height * ($reqwidth / $width));
        }

        // Принудительное приведение размеров
        $reqwidth = (int)round($reqwidth);
        $reqheight = $reqheight === null ? null : (int)round($reqheight);

//        dd($orient, $width * $orient);

        $original_aspect = $width / $height;
        $thumb_aspect = $reqwidth / $reqheight;

        if ($type == 'crop') {
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
        } else if ($type == 'scale') {

            $ratio = min($reqwidth / $width, ($reqheight ?: PHP_FLOAT_MAX) / $height);

            $new_width = (int)round($width * $ratio);
            $new_height = (int)round($height * $ratio);

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


        return $result;
    }
}
