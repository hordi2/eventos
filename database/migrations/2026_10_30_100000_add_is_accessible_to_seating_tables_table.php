<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque une table comme accessible (PMR) pour le placement automatique et
 * l'affichage (plan de table, vue d'ensemble, export PDF).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_tables', function (Blueprint $table): void {
            $table->boolean('is_accessible')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('seating_tables', function (Blueprint $table): void {
            $table->dropColumn('is_accessible');
        });
    }
};
