<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QR codes (§4.6 CLAUDE.md, T-055) : qr_jti est l'identifiant unique
 * révocable porté par le JWT du billet (claim "jti"). Régénéré à chaque
 * réémission (ReissueTicketQr) — l'ancien JWT reste signé valide mais ne
 * correspond plus au jti courant, donc rejeté à la vérification.
 * Jamais l'ID séquentiel du billet seul dans le QR (règle 4.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('qr_jti')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('qr_jti');
        });
    }
};
