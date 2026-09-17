<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\RegistrationFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime un fichier joint : la ligne (suppression logique, §4.5), puis le
 * fichier sur le disque une fois la transaction validée — jamais avant, pour
 * ne pas perdre un fichier qu'un rollback aurait gardé.
 */
final class DeleteRegistrationFile
{
    public function handle(RegistrationFile $file): void
    {
        $file->delete();

        DB::afterCommit(fn () => Storage::disk($file->disk)->delete($file->path));
    }
}
