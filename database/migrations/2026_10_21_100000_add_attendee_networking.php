<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le reste du networking entre participants (D8) : les centres d'intérêt qui
 * nourrissent les suggestions de mise en relation, les rendez-vous pris
 * pendant l'événement, et la messagerie interne.
 *
 * Tout cela ne vit que pour les participants inscrits à l'annuaire : sans
 * consentement, personne n'est suggéré, ni joignable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // La messagerie s'ouvre à part : un organisateur peut vouloir
            // l'annuaire sans la boîte aux lettres.
            $table->boolean('has_attendee_messaging')->default(false)->after('has_attendee_directory');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            // Liste de mots simples, normalisés en minuscules.
            $table->jsonb('directory_interests')->nullable();
        });

        Schema::create('attendee_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('requester_registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('guest_registration_id')->constrained('registrations')->cascadeOnDelete();

            // En UTC, comme toute date (règle 4.3) ; l'affichage se fait dans
            // le fuseau de l'événement.
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('place', 120)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
            $table->index(['guest_registration_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('attendee_meetings');

        Schema::create('attendee_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('from_registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('to_registration_id')->constrained('registrations')->cascadeOnDelete();

            $table->text('body');
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
            $table->index(['to_registration_id', 'read_at']);
        });

        OrganizationRowLevelSecurity::enable('attendee_messages');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('attendee_messages');
        Schema::dropIfExists('attendee_messages');
        OrganizationRowLevelSecurity::disable('attendee_meetings');
        Schema::dropIfExists('attendee_meetings');

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn('directory_interests');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('has_attendee_messaging');
        });
    }
};
