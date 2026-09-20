<?php

declare(strict_types=1);

final class LocalEnhancer
{
    public function enhance(string $src, string $dest, int $scale): string
    {
        if (extension_loaded('imagick')) {
            return $this->imagick($src, $dest, $scale);
        }
        return $this->gd($src, $dest, $scale);
    }

    private function imagick(string $src, string $dest, int $scale): string
    {
        $im = new Imagick($src);
        $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        try {
            $im->autoOrient();
        } catch (Throwable) {
        }

        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        $im->resizeImage($w * $scale, $h * $scale, Imagick::FILTER_LANCZOS, 1);

        $im->unsharpMaskImage(0, 0.8, 0.9, 0.02);
        $im->modulateImage(103, 112, 100);
        $im->contrastImage(true);
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality(92);
        $im->stripImage();
        $im->writeImage($dest);
        $im->clear();
        $im->destroy();
        return $dest;
    }

    private function gd(string $src, string $dest, int $scale): string
    {
        $info = getimagesize($src);
        if ($info === false) {
            throw new RuntimeException('Not a valid image');
        }
        $im = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($src),
            IMAGETYPE_PNG => imagecreatefrompng($src),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($src) : false,
            default => false,
        };
        if ($im === false) {
            throw new RuntimeException('GD could not read this image type');
        }

        $w = imagesx($im);
        $h = imagesy($im);
        $out = imagecreatetruecolor($w * $scale, $h * $scale);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $w * $scale, $h * $scale, $w, $h);
        imagefilter($out, IMG_FILTER_CONTRAST, -8);
        imagefilter($out, IMG_FILTER_BRIGHTNESS, 6);
        if (defined('IMG_FILTER_SMOOTH')) {
            imagefilter($out, IMG_FILTER_SMOOTH, 1);
        }
        imagejpeg($out, $dest, 92);
        imagedestroy($im);
        imagedestroy($out);
        return $dest;
    }
}
