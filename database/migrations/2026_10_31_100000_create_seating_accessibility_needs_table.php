<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Besoin d'accès PMR d'un invité, posé par l'organisateur (jamais déclaré
 * par l'invité lui-même via un formulaire public) pour le placement
 * automatique (AutoPlaceGuests) et le plan de table. event_id/guest_id
 * restent de simples colonnes (section 3 du CLAUDE.md) : la table couvre
 * aussi bien un invité RSVP (Domain/Form) qu'un billet (Domain/Ticketing).
 *
 * Une donnée de santé au sens du RGPD (art. 9) : l'existence de la ligne
 * EST l'information, aucune colonne identifiante à sa charge (même
 * raisonnement que seat_assignments/seating_constraints, non plus
 * parcourues par AnonymizeContact) — retirée par simple suppression de la
 * ligne, jamais par un champ à vider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_accessibility_needs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->string('guest_type');
            $table->bigInteger('guest_id');

            $table->timestamp('created_at')->nullable();

            $table->unique(['event_id', 'guest_type', 'guest_id']);
            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('seating_accessibility_needs');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('seating_accessibility_needs');
        Schema::dropIfExists('seating_accessibility_needs');
    }
};
