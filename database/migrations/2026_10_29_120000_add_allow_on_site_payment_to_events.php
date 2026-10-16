<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le paiement à l'arrivée (D3, T-054) était jusqu'ici toujours proposé à
 * l'invité, sans aucun moyen pour l'organisateur de le désactiver — par
 * défaut à true pour ne rien changer au comportement existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('allow_on_site_payment')->default(true)->after('allow_guest_edit');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('allow_on_site_payment');
        });
    }
};
