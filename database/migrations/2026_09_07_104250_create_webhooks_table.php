<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhooks sortants (page Paramètres → Intégrations & API) : un organisateur
 * connecte Itaza à un outil externe (Zapier, script maison) en souscrivant
 * à un ou plusieurs événements métier. Le secret sert à signer chaque
 * livraison (HMAC-SHA256, même principe que les webhooks entrants Stripe/
 * Twilio, dans l'autre sens) — l'organisateur ne le voit qu'une fois, à la
 * création (comme un jeton API).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret');
            $table->jsonb('subscribed_events');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_delivery_at')->nullable();
            $table->string('last_delivery_status')->nullable();
            $table->softDeletesTz();
            $table->timestamps();

            $table->index('organization_id');
        });

        OrganizationRowLevelSecurity::enable('webhooks');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('webhooks');
        Schema::dropIfExists('webhooks');
    }
};
