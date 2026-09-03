<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'idempotence des webhooks (règle 4.4 du CLAUDE.md). Ni
 * organization_id ni RLS ici : un webhook arrive avant qu'on sache à quelle
 * organisation il se rapporte (elle est retrouvée ensuite via
 * provider_message_id sur email_messages) — même raisonnement que
 * audit_logs, voir son propre docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_webhook_events', function (Blueprint $table): void {
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
        Schema::dropIfExists('email_webhook_events');
    }
};
