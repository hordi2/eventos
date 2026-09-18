<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liste d'invités d'un événement (UC-03, M3.5) : un contact de
 * l'organisation, invité à cet événement, avec ce que l'invitation lui
 * accorde. Le groupe est propre à la liste de l'événement — « ces personnes
 * répondent ensemble » — et ne remplace pas le foyer du contact, notion
 * durable de l'organisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invitees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Simple colonne côté modèle : Domain/Contact ne dépend pas de Domain/Event.
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // Import d'origine, pour relier une ligne du rapport à son invité.
            $table->foreignId('contact_import_id')->nullable()->constrained()->nullOnDelete();

            $table->string('group_key', 50)->nullable();
            // Nul = illimité, dans la limite de la plateforme (FormSettings::MAX_COMPANIONS).
            $table->unsignedSmallInteger('companions_allowed')->nullable()->default(0);
            $table->string('cc_email')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
            $table->index(['event_id', 'group_key']);
        });

        // Un contact n'apparaît qu'une fois dans la liste d'un événement :
        // réimporter le même fichier met l'invité à jour (règle 4.4).
        DB::statement('create unique index event_invitees_event_contact_unique on event_invitees (event_id, contact_id) where deleted_at is null');

        OrganizationRowLevelSecurity::enable('event_invitees');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('event_invitees');
        Schema::dropIfExists('event_invitees');
    }
};
