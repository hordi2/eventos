<?php

declare(strict_types=1);

namespace App\Support\Images;

use GdImage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ramène une image envoyée par un organisateur à une largeur maximale et la
 * réencode : c'est ce qui tient la contrainte de poids d'une page invité
 * (< 500 Ko, premier rendu < 2 s en 3G — §2 du CLAUDE.md) sans demander à
 * l'organisateur de préparer son fichier.
 *
 * Écrit avec GD plutôt qu'avec une dépendance : un redimensionnement et un
 * réencodage suffisent. L'extension gd est donc requise (image Docker et CI).
 */
final class ResizeUploadedImage
{
    public const MAX_WIDTH = 1600;

    private const JPEG_QUALITY = 82;

    public function handle(UploadedFile $image, int $maxWidth = self::MAX_WIDTH): ResizedImage
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException("L'extension PHP gd est requise pour redimensionner les images.");
        }

        $source = @imagecreatefromstring((string) file_get_contents($image->getRealPath()));

        if ($source === false) {
            throw new InvalidArgumentException("Ce fichier n'est pas une image lisible.");
        }

        $source = $this->straighten($source, $image);
        $source = $this->downscale($source, $maxWidth);

        // Une image à transparence reste en PNG ; tout le reste part en JPEG,
        // nettement plus léger à qualité équivalente pour une photo.
        return imageistruecolor($source) && $this->hasAlpha($image)
            ? $this->encodePng($source)
            : $this->encodeJpeg($source);
    }

    /**
     * Photo prise à l'horizontale sur un téléphone : l'orientation vit dans
     * les données EXIF, que le réencodage perdrait.
     */
    private function straighten(GdImage $image, UploadedFile $upload): GdImage
    {
        if (! extension_loaded('exif') || ! in_array($upload->getMimeType(), ['image/jpeg', 'image/tiff'], true)) {
            return $image;
        }

        $exif = @exif_read_data($upload->getRealPath());
        $rotation = match ($exif['Orientation'] ?? null) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($rotation === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $rotation, 0);

        return $rotated === false ? $image : $rotated;
    }

    private function downscale(GdImage $image, int $maxWidth): GdImage
    {
        if (imagesx($image) <= $maxWidth) {
            return $image;
        }

        $scaled = imagescale($image, $maxWidth);

        return $scaled === false ? $image : $scaled;
    }

    private function hasAlpha(UploadedFile $upload): bool
    {
        return in_array($upload->getMimeType(), ['image/png', 'image/webp'], true);
    }

    private function encodePng(GdImage $image): ResizedImage
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return new ResizedImage($this->capture(fn (): bool => imagepng($image, null, 8)), 'png', imagesx($image), imagesy($image));
    }

    private function encodeJpeg(GdImage $image): ResizedImage
    {
        // Un JPEG n'a pas de canal alpha : la transparence devient du blanc
        // plutôt que du noir, plus discret sur une page claire.
        $flattened = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flattened, 0, 0, (int) imagecolorallocate($flattened, 255, 255, 255));
        imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return new ResizedImage($this->capture(fn (): bool => imagejpeg($flattened, null, self::JPEG_QUALITY)), 'jpg', imagesx($flattened), imagesy($flattened));
    }

    /**
     * @param  callable(): bool  $encode
     */
    private function capture(callable $encode): string
    {
        ob_start();
        $encoded = $encode();
        $content = (string) ob_get_clean();

        if (! $encoded || $content === '') {
            throw new RuntimeException("L'image n'a pas pu être réencodée.");
        }

        return $content;
    }
}
