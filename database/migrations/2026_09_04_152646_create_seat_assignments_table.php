<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affectation d'un invité à une table (T-065). guest_type/guest_id
 * identifient l'invité comme partout ailleurs dans Domain/CheckIn
 * (attendee_id ou ticket_id, jamais les deux — voir GuestData côté
 * Support/CheckIn). L'unicité (event_id, guest_type, guest_id) garantit
 * qu'un invité n'est jamais affecté à deux tables à la fois : une
 * réaffectation supprime d'abord l'ancienne ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->foreignId('seating_table_id')->constrained()->cascadeOnDelete();
            $table->string('guest_type');
            $table->bigInteger('guest_id');

            $table->timestamps();

            $table->unique(['event_id', 'guest_type', 'guest_id']);
            $table->index(['organization_id', 'event_id']);
            $table->index(['seating_table_id']);
        });

        OrganizationRowLevelSecurity::enable('seat_assignments');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('seat_assignments');
        Schema::dropIfExists('seat_assignments');
    }
};
