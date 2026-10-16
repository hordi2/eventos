<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remboursement partiel (M5.5) : cumul de ce qui a déjà été rendu à
 * l'acheteur, dans la devise de la commande (`total_currency`). Un
 * remboursement qui atteint le total fait encore passer `status` à
 * `refunded` (RefundOrder) — cette colonne ne remplace pas la machine à
 * états, elle permet simplement d'en rembourser une partie sans y entrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->bigInteger('refunded_amount_minor')->default(0)->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('refunded_amount_minor');
        });
    }
};
