<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charte graphique minimale de l'organisation (T-073, M6.2, scope MVP —
 * voir le backlog) : un logo et une couleur principale, appliqués à la page
 * événement publique (T-072) et à l'en-tête des e-mails. Pas de palette à
 * plusieurs couleurs, pas de typographie personnalisée, pas de CSS libre :
 * hors périmètre MVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('primary_color', 7)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'primary_color']);
        });
    }
};
