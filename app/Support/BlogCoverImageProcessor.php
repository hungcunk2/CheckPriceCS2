<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BlogCoverImageProcessor
{
    public const WIDTH = 1200;

    public const HEIGHT = 675;

    public function store(UploadedFile $file): string
    {
        $source = $this->loadImage($file);

        try {
            $processed = $this->centerCropResize($source);
            $relativePath = 'blog/covers/'.Str::uuid().'.jpg';
            $directory = Storage::disk('public')->path('blog/covers');

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            if (! imagejpeg($processed, Storage::disk('public')->path($relativePath), 85)) {
                throw new RuntimeException('Could not save cover image.');
            }

            return $relativePath;
        } finally {
            imagedestroy($source);
            if (isset($processed) && $processed instanceof GdImage) {
                imagedestroy($processed);
            }
        }
    }

    private function loadImage(UploadedFile $file): GdImage
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Could not read uploaded file.');
        }

        $image = match ($file->getMimeType()) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('Unsupported or invalid image file.');
        }

        return $image;
    }

    private function centerCropResize(GdImage $source): GdImage
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $targetRatio = self::WIDTH / self::HEIGHT;
        $srcRatio = $srcW / $srcH;

        if ($srcRatio > $targetRatio) {
            $cropW = (int) round($srcH * $targetRatio);
            $cropH = $srcH;
            $srcX = (int) round(($srcW - $cropW) / 2);
            $srcY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $srcX = 0;
            $srcY = (int) round(($srcH - $cropH) / 2);
        }

        $dest = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $white = imagecolorallocate($dest, 255, 255, 255);
        imagefill($dest, 0, 0, $white);
        imagecopyresampled($dest, $source, 0, 0, $srcX, $srcY, self::WIDTH, self::HEIGHT, $cropW, $cropH);

        return $dest;
    }
}
