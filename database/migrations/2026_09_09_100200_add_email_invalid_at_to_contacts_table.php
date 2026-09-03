<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinct de unsubscribed_at (choix de l'invité) : email_invalid_at est
 * posé automatiquement par le traitement des webhooks (bounce dur ou
 * plainte) — T-043, règle « bounce dur → contact marqué invalide, exclu
 * des envois suivants ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->timestamp('email_invalid_at')->nullable()->after('unsubscribed_at');
            $table->string('email_invalid_reason')->nullable()->after('email_invalid_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['email_invalid_at', 'email_invalid_reason']);
        });
    }
};
