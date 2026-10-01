<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Couverture de l'invitation entre les mains de l'organisateur : la
 * phrase d'ouverture, le mot manuscrit, le monogramme en filigrane,
 * l'intensité du voile sur la photo et le libellé du bouton. Tout est
 * facultatif : une page qui n'y touche pas garde la couverture d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('cover_eyebrow', 120)->nullable()->after('banner_path');
            $table->string('cover_script', 120)->nullable()->after('cover_eyebrow');
            $table->string('cover_monogram', 12)->nullable()->after('cover_script');
            // 0 : la photo nue. 90 : presque l'encre. Par défaut 50.
            $table->unsignedTinyInteger('cover_overlay')->default(50)->after('cover_monogram');
            $table->string('cover_cta_label', 60)->nullable()->after('cover_overlay');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['cover_eyebrow', 'cover_script', 'cover_monogram', 'cover_overlay', 'cover_cta_label']);
        });
    }
};
