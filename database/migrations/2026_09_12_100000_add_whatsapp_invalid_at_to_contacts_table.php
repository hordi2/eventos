<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Même raisonnement que email_invalid_at (T-043) : posé automatiquement
 * par le webhook Twilio sur un statut "failed"/"undelivered" (numéro
 * invalide ou plus joignable sur WhatsApp), jamais par un choix de
 * l'invité — whatsapp_consent porte déjà ce choix-là.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->timestamp('whatsapp_invalid_at')->nullable()->after('email_invalid_reason');
            $table->string('whatsapp_invalid_reason')->nullable()->after('whatsapp_invalid_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp_invalid_at', 'whatsapp_invalid_reason']);
        });
    }
};
