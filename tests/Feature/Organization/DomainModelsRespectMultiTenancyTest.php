<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Membership;
use App\Support\MultiTenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('tout modèle du domaine possédant organization_id déclare le trait BelongsToOrganization', function (): void {
    // Membership est la table de résolution du tenant lui-même : on ne peut
    // pas exiger un contexte d'organisation déjà résolu pour la consulter,
    // c'est justement elle qui sert à le déterminer. Exception documentée,
    // pas un oubli.
    $exempt = [
        Membership::class,
    ];

    $domainPath = app_path('Domain');

    if (! File::exists($domainPath)) {
        expect(true)->toBeTrue();

        return;
    }

    $modelClasses = collect(File::allFiles($domainPath))
        ->filter(fn ($file) => Str::contains($file->getPathname(), DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR))
        ->map(function ($file) {
            $relative = Str::after($file->getPathname(), app_path().DIRECTORY_SEPARATOR);

            return 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
        })
        ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Model::class));

    foreach ($modelClasses as $class) {
        if (in_array($class, $exempt, true)) {
            continue;
        }

        $instance = new $class;

        if (Schema::hasColumn($instance->getTable(), 'organization_id')) {
            expect(class_uses_recursive($class))
                ->toContain(BelongsToOrganization::class, "{$class} a une colonne organization_id mais n'utilise pas BelongsToOrganization.");
        }
    }

    // Garantit une assertion même quand aucun modèle métier n'existe encore
    // (T-002) ou qu'aucun n'a de colonne organization_id à vérifier ce jour-là.
    expect(true)->toBeTrue();
});
