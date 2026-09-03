<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Aucun prestataire n'est encore intégré (Stripe = T-052,
            // Mobile Money = T-053, espèces = T-054) : provider est une
            // simple étiquette pour l'instant, ex. "stripe"/"mobile_money"/
            // "cash", fournie par l'appelant.
            $table->string('provider');
            // Idempotence des webhooks de paiement (§4.4 CLAUDE.md) : un
            // provider_payment_id déjà traité n'est jamais rejoué. Null
            // tant qu'aucune confirmation n'est revenue du prestataire.
            $table->string('provider_payment_id')->nullable()->unique();

            $table->string('status')->default('pending');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('failure_reason')->nullable();

            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'order_id']);
        });

        OrganizationRowLevelSecurity::enable('payments');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('payments');
        Schema::dropIfExists('payments');
    }
};
