<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcours de réponse d'un événement réservé à sa liste d'invités :
 * - le brouillon retient l'invitation qui l'a ouvert (simple colonne :
 *   Domain/Form ne dépend pas de Domain/Contact) ;
 * - chaque participant peut être relié à son contact, pour qu'un membre de
 *   groupe venu avec un autre invité apparaisse comme ayant répondu, et
 *   qu'un effacement RGPD l'atteigne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_drafts', function (Blueprint $table): void {
            $table->foreignId('event_invitee_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
        });

        Schema::table('attendees', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->after('registration_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'contact_id']);
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::table('registration_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_invitee_id');
        });
    }
};
