<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode d'affichage (page Paramètres → Personnalisation) : le mode sombre
 * n'est plus piloté par la préférence système (voir app.css) mais par ce
 * réglage explicite par organisation, « normal » par défaut — décision
 * prise avec l'utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('theme_mode')->default('light')->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('theme_mode');
        });
    }
};
