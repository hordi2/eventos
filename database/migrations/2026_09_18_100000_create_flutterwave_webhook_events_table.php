<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'idempotence des webhooks Flutterwave (règle 4.4 du CLAUDE.md) —
 * même structure que stripe_webhook_events (T-052).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flutterwave_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->jsonb('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flutterwave_webhook_events');
    }
};
