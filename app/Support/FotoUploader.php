<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves product and profile photos as compact JPEGs on the public disk.
 *
 * Phone photos are 3-5 MB; shrunk to 1200 px at quality 80 they end up around 100-250 KB,
 * so even hundreds of photos stay far below InfinityFree's 5 GB quota.
 */
class FotoUploader
{
    public const MAX_SIDE = 1200;

    public const QUALITY = 80;

    /** Upload rules shared by every photo field (5 MB, and a pixel cap so GD stays within PHP's memory limit). */
    public const RULES = ['nullable', 'image', 'max:5120', 'dimensions:max_width=6000,max_height=6000'];

    public static function simpan(UploadedFile $file, string $folder): string
    {
        $image = @imagecreatefromstring($file->get());
        if ($image === false) {
            return $file->store($folder, 'public'); // a format GD cannot read: keep the original file
        }

        $image = self::shrink(self::upright($image, $file->getRealPath()));

        ob_start();
        imagejpeg($image, null, self::QUALITY);
        $path = $folder.'/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }

    /** Phones store portrait shots as landscape pixels plus an EXIF Orientation tag. */
    private static function upright(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);

        return match ((int) (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    /** Fits the longest side into MAX_SIDE, on a white canvas because JPEG has no transparency. */
    private static function shrink(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }
}
