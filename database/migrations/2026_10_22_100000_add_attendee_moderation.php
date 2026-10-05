<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modération de la messagerie entre participants (D8).
 *
 * Trois recours, du plus immédiat au plus lourd : le participant bloque qui
 * l'importune, lui-même et tout de suite ; il signale un message à
 * l'organisateur ; l'organisateur retire le message ou suspend l'envoi pour
 * ce participant.
 *
 * Un message retiré n'est pas effacé : son texte reste pour que le
 * signalement garde un sens, mais il ne s'affiche plus à personne d'autre
 * que l'organisateur qui le traite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->timestamp('messaging_suspended_at')->nullable();
        });

        Schema::table('attendee_messages', function (Blueprint $table): void {
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('attendee_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('blocker_registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('blocked_registration_id')->constrained('registrations')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['blocker_registration_id', 'blocked_registration_id'], 'attendee_blocks_pair_unique');
            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('attendee_blocks');

        Schema::create('attendee_message_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('attendee_message_id')->constrained('attendee_messages')->cascadeOnDelete();
            $table->foreignId('reporter_registration_id')->constrained('registrations')->cascadeOnDelete();

            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            // Un même message ne se signale qu'une fois par personne.
            $table->unique(['attendee_message_id', 'reporter_registration_id'], 'attendee_reports_unique');
            $table->index(['organization_id', 'event_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('attendee_message_reports');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('attendee_message_reports');
        Schema::dropIfExists('attendee_message_reports');
        OrganizationRowLevelSecurity::disable('attendee_blocks');
        Schema::dropIfExists('attendee_blocks');

        Schema::table('attendee_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('removed_by');
            $table->dropColumn('removed_at');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn('messaging_suspended_at');
        });
    }
};
