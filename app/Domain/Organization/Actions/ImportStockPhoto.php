<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationImage;
use App\Models\User;
use App\Support\Images\PexelsClient;
use App\Support\Images\StockPhotosUnavailableException;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Copie une photo libre de droits dans « Mes images ». Elle suit ensuite le
 * même chemin qu'une image envoyée (redimensionnement, rangement, ligne en
 * bibliothèque) : la page invité la sert depuis notre stockage, sans jamais
 * contacter Pexels. Le nom garde le crédit du photographe.
 */
final class ImportStockPhoto
{
    public function __construct(
        private readonly PexelsClient $pexelsClient,
        private readonly StoreOrganizationImage $storeOrganizationImage,
    ) {}

    public function handle(Organization $organization, User $importer, int $photoId): OrganizationImage
    {
        $photo = $this->pexelsClient->download($photoId);
        $temporary = (string) tempnam(sys_get_temp_dir(), 'pexels');
        file_put_contents($temporary, $photo['content']);

        try {
            $file = new UploadedFile($temporary, "Photo de {$photo['photographer']} (Pexels).{$photo['extension']}", null, null, true);

            return $this->storeOrganizationImage->handle($organization, $importer, $file);
        } catch (InvalidArgumentException) {
            throw StockPhotosUnavailableException::unreachable();
        } finally {
            @unlink($temporary);
        }
    }
}
