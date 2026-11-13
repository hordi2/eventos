<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pixel Meta (Facebook, Instagram) sur les pages invité (M9.1). Comme la
 * mesure GA4, il ne se charge qu'après l'accord de l'invité (§10.2, RGPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('meta_pixel_id', 20)->nullable()->after('ga4_measurement_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('meta_pixel_id');
        });
    }
};
