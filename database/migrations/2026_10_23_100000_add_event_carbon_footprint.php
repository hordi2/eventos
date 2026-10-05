<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empreinte carbone d'un événement (D12). Trois sources, celles du cahier
 * des charges : les déplacements déclarés par les participants, les repas
 * servis et les impressions.
 *
 * Les déplacements viennent des participants eux-mêmes — personne ne sait à
 * leur place comment ils sont venus —, les repas et les impressions de
 * l'organisateur, qui seul les connaît.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('has_carbon_report')->default(false)->after('has_attendee_messaging');
            $table->unsignedInteger('meals_served')->nullable();
            $table->unsignedInteger('printed_pages')->nullable();
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('travel_mode', 20)->nullable();
            $table->unsignedInteger('travel_distance_km')->nullable();
            $table->string('travel_city', 80)->nullable();
            // « offers » : propose des places. « seeks » : en cherche une.
            $table->string('carpool_role', 10)->nullable();
            $table->timestamp('travel_declared_at')->nullable();

            $table->index(['event_id', 'travel_declared_at']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex(['event_id', 'travel_declared_at']);
            $table->dropColumn(['travel_mode', 'travel_distance_km', 'travel_city', 'carpool_role', 'travel_declared_at']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['has_carbon_report', 'meals_served', 'printed_pages']);
        });
    }
};
