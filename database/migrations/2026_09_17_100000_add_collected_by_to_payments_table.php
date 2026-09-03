<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement à l'arrivée (D3, T-054) : renseigné uniquement pour un
 * encaissement en espèces au check-in — qui l'a encaissé, pour le rapport
 * de caisse ("Encaissement tracé : montant, opérateur, horodatage").
 * Null pour tout paiement en ligne (Stripe, Mobile Money).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('collected_by')->nullable()->after('provider')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collected_by');
        });
    }
};
