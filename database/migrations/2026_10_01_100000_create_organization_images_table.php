<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Bibliothèque d'images d'une organisation (onglet « Mes images » du
 * constructeur) : logo, image de fond et images des blocs y sont enregistrées
 * une fois, puis réutilisées d'un formulaire à l'autre.
 *
 * La reprise ci-dessous inscrit les images déjà envoyées avant cette table,
 * sinon l'onglet s'ouvrirait vide chez un organisateur qui a pourtant un logo.
 * Les dimensions restent nulles pour ces lignes : les lire supposerait de
 * télécharger chaque fichier depuis le stockage, ce qu'une migration n'a pas
 * à faire — l'affichage sait se passer de la largeur et de la hauteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('disk');
            $table->string('path')->unique();
            $table->string('original_name')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'created_at']);
        });

        OrganizationRowLevelSecurity::enable('organization_images');

        $this->backfillExistingImages();
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('organization_images');
        Schema::dropIfExists('organization_images');
    }

    /**
     * La row-level security s'applique aussi au propriétaire des tables : on
     * se place donc explicitement dans chaque organisation, l'une après
     * l'autre, pour lire ses formulaires et écrire ses images.
     */
    private function backfillExistingImages(): void
    {
        $now = now();

        foreach (DB::table('organizations')->orderBy('id')->pluck('id') as $organizationId) {
            DB::select("select set_config('app.current_organization_id', ?, false)", [(string) $organizationId]);

            $paths = collect(DB::table('forms')->pluck('settings'))
                ->flatMap(function (?string $settings): array {
                    $theme = json_decode((string) $settings, true)['theme'] ?? [];

                    return is_array($theme) ? [$theme['logo_path'] ?? null, $theme['background_image_path'] ?? null] : [];
                })
                ->merge(DB::table('form_fields')->pluck('config')->map(
                    fn (?string $config): mixed => json_decode((string) $config, true)['image_path'] ?? null
                ))
                ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
                ->unique()
                // Une version déjà publiée peut désigner un fichier effacé du
                // temps où retirer une image la supprimait : l'inscrire ne
                // ferait qu'afficher une vignette cassée dans la bibliothèque.
                ->filter(fn (string $path): bool => Storage::disk('public')->exists($path))
                ->values();

            if ($paths->isEmpty()) {
                continue;
            }

            DB::table('organization_images')->insertOrIgnore($paths->map(fn (string $path): array => [
                'organization_id' => $organizationId,
                'uploaded_by' => null,
                'disk' => 'public',
                'path' => $path,
                'original_name' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }

        DB::select("select set_config('app.current_organization_id', '', false)");
    }
};
