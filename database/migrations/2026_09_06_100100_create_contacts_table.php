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
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->string('preferred_language')->nullable();
            $table->string('preferred_channel')->nullable();
            $table->jsonb('custom_fields')->nullable();

            // Un consentement par canal, chacun avec sa propre source et sa
            // propre date (T-040 : « consentement e-mail / SMS / WhatsApp
            // distincts, avec source et date ») — plus précis que la paire
            // consent_source/consent_at unique esquissée au §7.2 du CDC.
            $table->boolean('email_consent')->default(false);
            $table->string('email_consent_source')->nullable();
            $table->timestamp('email_consent_at')->nullable();
            $table->boolean('sms_consent')->default(false);
            $table->string('sms_consent_source')->nullable();
            $table->timestamp('sms_consent_at')->nullable();
            $table->boolean('whatsapp_consent')->default(false);
            $table->string('whatsapp_consent_source')->nullable();
            $table->timestamp('whatsapp_consent_at')->nullable();

            $table->timestamp('unsubscribed_at')->nullable();
            $table->unsignedInteger('engagement_score')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'last_name', 'first_name']);
            $table->index(['organization_id', 'household_id']);
        });

        OrganizationRowLevelSecurity::enable('contacts');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('contacts');
        Schema::dropIfExists('contacts');
    }
};
