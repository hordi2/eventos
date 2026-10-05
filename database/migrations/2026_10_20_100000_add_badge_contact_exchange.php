<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Échange de coordonnées par scan de badge (D8). Deux participants se
 * rencontrent, l'un scanne le badge de l'autre, et chacun retrouve l'autre
 * dans ses rencontres.
 *
 * Le jeton du badge est propre au networking : jamais celui du code
 * d'entrée, qui est à usage unique et sert au contrôle à l'accueil
 * (règle 4.6). Partager ses coordonnées est un second consentement, distinct
 * de celui de l'annuaire : on peut figurer à l'annuaire sans donner son
 * adresse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->uuid('networking_token')->nullable()->unique();
            $table->boolean('shares_contact')->default(false);
        });

        Schema::create('attendee_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('scanner_registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('scanned_registration_id')->constrained('registrations')->cascadeOnDelete();

            $table->timestamps();

            // Une rencontre ne se compte qu'une fois, même si le badge est
            // scanné dix fois (règle 4.4).
            $table->unique(['scanner_registration_id', 'scanned_registration_id'], 'attendee_connections_pair_unique');
            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('attendee_connections');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('attendee_connections');
        Schema::dropIfExists('attendee_connections');

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn(['networking_token', 'shares_contact']);
        });
    }
};
