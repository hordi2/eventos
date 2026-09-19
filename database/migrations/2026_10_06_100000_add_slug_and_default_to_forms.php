<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Plusieurs formulaires par événement : chacun a son lien
 * (/r/{organisation}/{événement}/f/{slug}), et un seul, « par défaut »,
 * répond au lien de l'événement lui-même. Le formulaire déjà présent de
 * chaque événement devient ce formulaire par défaut : ses liens déjà
 * envoyés restent valables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->string('slug', 80)->nullable()->after('name');
            $table->boolean('is_default')->default(false)->after('slug');
        });

        // La RLS s'applique aussi au propriétaire : on se place dans chaque
        // organisation pour compléter ses formulaires.
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            DB::select("select set_config('app.current_organization_id', ?, false)", [(string) $organizationId]);

            $forms = DB::table('forms')->whereNull('deleted_at')->orderBy('id')->get(['id', 'event_id', 'name']);

            foreach ($forms->groupBy('event_id') as $eventForms) {
                $taken = [];

                foreach ($eventForms->values() as $index => $form) {
                    $base = Str::limit(Str::slug((string) $form->name), 70, '') ?: 'formulaire';
                    $slug = $base;

                    for ($suffix = 2; in_array($slug, $taken, true); $suffix++) {
                        $slug = "{$base}-{$suffix}";
                    }

                    $taken[] = $slug;
                    DB::table('forms')->where('id', $form->id)->update(['slug' => $slug, 'is_default' => $index === 0]);
                }
            }

            DB::table('forms')->whereNotNull('deleted_at')->whereNull('slug')->update(['slug' => DB::raw("'supprime-' || id")]);
        }

        DB::select("select set_config('app.current_organization_id', '', false)");

        DB::statement('ALTER TABLE forms ALTER COLUMN slug SET NOT NULL');
        // Uniques parmi les formulaires en service : un formulaire supprimé
        // libère son lien et sa place de formulaire par défaut.
        DB::statement('CREATE UNIQUE INDEX forms_event_slug_unique ON forms (event_id, slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX forms_event_default_unique ON forms (event_id) WHERE is_default AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS forms_event_default_unique');
        DB::statement('DROP INDEX IF EXISTS forms_event_slug_unique');

        Schema::table('forms', function (Blueprint $table): void {
            $table->dropColumn(['slug', 'is_default']);
        });
    }
};
