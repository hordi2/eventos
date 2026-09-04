<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contraintes « doit être avec » / « ne doit pas être avec » (M7.4, T-065),
 * consommées par AutoPlaceGuests. Une paire d'invités (guest_a, guest_b) —
 * l'ordre n'a pas de sens métier, mais est normalisé côté application
 * (guest_a toujours "avant" guest_b) pour qu'une contrainte ne puisse pas
 * être enregistrée deux fois dans les deux sens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_constraints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->string('guest_a_type');
            $table->bigInteger('guest_a_id');
            $table->string('guest_b_type');
            $table->bigInteger('guest_b_id');
            $table->string('type');

            $table->timestamps();

            $table->unique(['event_id', 'guest_a_type', 'guest_a_id', 'guest_b_type', 'guest_b_id']);
            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('seating_constraints');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('seating_constraints');
        Schema::dropIfExists('seating_constraints');
    }
};
