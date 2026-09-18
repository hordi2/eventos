<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\OrganizationImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Suppression définitive d'une image de la bibliothèque. L'appelant a déjà
 * vérifié qu'elle ne sert plus nulle part (FindOrganizationImageUsage) : une
 * image encore utilisée laisserait un emplacement cassé derrière elle.
 *
 * Le fichier ne part qu'après le commit, pour ne pas disparaître du disque si
 * la transaction est annulée.
 */
final class DeleteOrganizationImage
{
    public function handle(OrganizationImage $image): void
    {
        $path = $image->path;
        $disk = $image->disk;

        DB::transaction(function () use ($image, $disk, $path): void {
            $image->delete();

            DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
        });
    }
}
