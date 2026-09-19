<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envoi groupé des invitations depuis la liste d'invités :
 * - la liste retient le dernier envoi fait à chaque invité (date et canal),
 *   pour savoir qui a déjà reçu son invitation ;
 * - un e-mail peut porter une adresse en copie (« e-mail en copie » de
 *   l'invité : une assistante, un conjoint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_invitees', function (Blueprint $table): void {
            $table->timestamp('last_invited_at')->nullable()->after('cc_email');
            $table->string('last_invited_via', 20)->nullable()->after('last_invited_at');
        });

        Schema::table('email_messages', function (Blueprint $table): void {
            $table->string('cc_email')->nullable()->after('to_email');
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table): void {
            $table->dropColumn('cc_email');
        });

        Schema::table('event_invitees', function (Blueprint $table): void {
            $table->dropColumn(['last_invited_at', 'last_invited_via']);
        });
    }
};
