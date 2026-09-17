<?php

declare(strict_types=1);

use App\Domain\Form\Support\FileUploadAnswer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

it('accepte un PDF et refuse un faux PDF, une extension trompeuse ou un fichier trop lourd', function (): void {
    $rules = FileUploadAnswer::uploadRules(['file_types' => ['pdf'], 'max_size_mb' => 1]);
    $passes = fn (UploadedFile $file): bool => Validator::make(['fichier' => $file], ['fichier' => $rules])->passes();

    expect($passes(UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf')))->toBeTrue();
    // Un exécutable renommé en .pdf : le type lu dans le contenu le trahit.
    expect($passes(UploadedFile::fake()->create('cv.pdf', 200, 'application/x-msdownload')))->toBeFalse();
    expect($passes(UploadedFile::fake()->create('cv.exe', 200, 'application/pdf')))->toBeFalse();
    expect($passes(UploadedFile::fake()->create('cv.pdf', 1500, 'application/pdf')))->toBeFalse();
});

it('propose images et PDF par défaut, plafonne la taille et écrit les tailles en calcul entier', function (): void {
    expect(FileUploadAnswer::types([]))->toBe(['images', 'pdf']);
    expect(FileUploadAnswer::types(['file_types' => ['documents', 'inconnu']]))->toBe(['documents']);
    expect(FileUploadAnswer::maxSizeMb(['max_size_mb' => 50]))->toBe(10);
    expect(FileUploadAnswer::acceptAttribute(['file_types' => ['pdf']]))->toBe('.pdf');
    expect(FileUploadAnswer::formatSize(850 * 1024))->toBe('850 Ko');
    expect(FileUploadAnswer::formatSize(2_569_011))->toBe('2,4 Mo');
});
