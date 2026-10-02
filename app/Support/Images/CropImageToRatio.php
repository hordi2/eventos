<?php

declare(strict_types=1);

namespace App\Support\Images;

use GdImage;

/**
 * Recadre une image au format d'un cadre, en gardant son centre — ce que le
 * navigateur fait avec object-fit: cover, et que dompdf ne sait pas faire :
 * sans cela, une photo portrait posée dans un cadre paysage s'étire.
 *
 * Le faire-part doit montrer exactement le même cadrage que la page web.
 */
final class CropImageToRatio
{
    /**
     * Renvoie une image JPEG recadrée, ou null si le contenu n'est pas une
     * image lisible : le faire-part sort alors sans elle plutôt que pas du
     * tout.
     */
    public function handle(string $contents, int $ratioWidth, int $ratioHeight, int $maxWidth = 1000): ?string
    {
        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $ratio = $ratioWidth / $ratioHeight;

        // La plus grande fenêtre au bon format qui tienne dans la photo.
        $cropWidth = min($sourceWidth, (int) round($sourceHeight * $ratio));
        $cropHeight = min($sourceHeight, (int) round($sourceWidth / $ratio));
        $left = (int) round(($sourceWidth - $cropWidth) / 2);
        $top = (int) round(($sourceHeight - $cropHeight) / 2);

        $targetWidth = min($maxWidth, $cropWidth);
        $targetHeight = max(1, (int) round($targetWidth / $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($target, $source, 0, 0, $left, $top, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
        imagedestroy($source);

        ob_start();
        imagejpeg($target, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($target);

        return $jpeg === '' ? null : $jpeg;
    }
}
