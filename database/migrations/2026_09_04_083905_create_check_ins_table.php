<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des scans de check-in (M7.1, T-060). event_id/attendee_id/
 * ticket_id restent de simples colonnes : Domain/CheckIn ne référence
 * jamais un modèle de Domain/Event, Domain/Form ou Domain/Ticketing
 * (section 3 du CLAUDE.md). Exactement une des deux colonnes
 * attendee_id/ticket_id est renseignée selon la nature de l'invité
 * (contrainte CHECK, num_nonnulls n'existe qu'en PostgreSQL).
 *
 * device_local_id est unique : c'est la clé d'idempotence de la
 * synchronisation par lot (§4.4 du CLAUDE.md) — rejouer le même scan
 * après une resynchronisation ne crée jamais de doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->bigInteger('attendee_id')->nullable();
            $table->bigInteger('ticket_id')->nullable();

            $table->uuid('device_local_id')->unique();
            $table->string('direction');
            $table->string('status');
            $table->timestamp('recorded_at');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'event_id']);
            $table->index(['attendee_id']);
            $table->index(['ticket_id']);
        });

        DB::statement('alter table check_ins add constraint check_ins_exactly_one_guest check (num_nonnulls(attendee_id, ticket_id) = 1)');

        OrganizationRowLevelSecurity::enable('check_ins');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('check_ins');
        Schema::dropIfExists('check_ins');
    }
};
