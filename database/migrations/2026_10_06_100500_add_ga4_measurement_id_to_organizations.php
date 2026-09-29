<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesure d'audience Google Analytics 4 sur les pages invité (lot 2).
 * Renseignée, elle n'est chargée qu'après l'accord de l'invité : la
 * mesure pose des cookies (§10.2, RGPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('ga4_measurement_id', 20)->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('ga4_measurement_id');
        });
    }
};
