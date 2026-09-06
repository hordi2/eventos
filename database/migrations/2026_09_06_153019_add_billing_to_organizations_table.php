<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan et abonnement Stripe de l'organisation (T-074, M0.4). `plan` reste la
 * source de vérité même en cas d'échec de paiement prolongé : la
 * "restriction" (AC : « échec de prélèvement... puis restriction ») est un
 * calcul à la volée (voir GetEffectivePlan), jamais un changement destructif
 * de `plan` — l'organisation retrouve son plan payant dès le paiement
 * régularisé, sans reconfiguration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('plan')->default('free')->after('primary_color');
            $table->string('stripe_customer_id')->nullable()->after('plan');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            $table->string('subscription_status')->nullable()->after('stripe_subscription_id');
            $table->timestamp('subscription_current_period_end')->nullable()->after('subscription_status');
            $table->timestamp('payment_failed_at')->nullable()->after('subscription_current_period_end');
            $table->unsignedTinyInteger('dunning_stage')->default(0)->after('payment_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'plan',
                'stripe_customer_id',
                'stripe_subscription_id',
                'subscription_status',
                'subscription_current_period_end',
                'payment_failed_at',
                'dunning_stage',
            ]);
        });
    }
};
