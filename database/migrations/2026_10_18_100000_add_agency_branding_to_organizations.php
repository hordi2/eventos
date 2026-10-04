<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque de l'agence sur les portails de ses clients (D10, white-label total
 * du §M6.3). Quand l'agence l'active, ses comptes clients voient son logo et
 * sa couleur dans leur back-office, à la place de ceux d'Itaza.
 *
 * Le réglage vit sur l'agence : elle décide pour tout son portefeuille.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('brands_client_portals')->default(false)->after('is_agency');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('brands_client_portals');
        });
    }
};
