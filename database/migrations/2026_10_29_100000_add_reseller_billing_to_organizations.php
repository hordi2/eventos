<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moteur revendeur du portail agence (D10) : facturation consolidée.
 *
 * `stripe_subscription_item_id` identifie la ligne de CETTE organisation au
 * sein de l'abonnement Stripe qui la porte — le sien (cas général) ou celui,
 * à plusieurs lignes, de l'agence qui la gère (un compte confié n'a plus son
 * propre abonnement : voir ManageClientSubscriptionItems). Remplace l'ancien
 * repli sur `items->data[0]`, fragile dès qu'un abonnement porte plus d'une
 * ligne.
 *
 * `resale_price_minor`/`resale_currency` n'ont de sens que pour un compte
 * confié : le tarif que l'agence a choisi de facturer à CE client, encaissé
 * par elle, hors Itaza. La différence avec le coût de gros du palier
 * (`config('plans.{plan}.price_minor')`) est sa marge — un calcul, jamais
 * une colonne stockée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('stripe_subscription_item_id')->nullable()->after('stripe_subscription_id');
            $table->unsignedBigInteger('resale_price_minor')->nullable()->after('managed_since');
            $table->char('resale_currency', 3)->nullable()->after('resale_price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['stripe_subscription_item_id', 'resale_price_minor', 'resale_currency']);
        });
    }
};
