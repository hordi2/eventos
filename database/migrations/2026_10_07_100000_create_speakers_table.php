<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intervenants d'un événement et salle de ses sessions (D6, lot 3) : de
 * quoi publier un vrai programme de conférence. Une session reste un
 * événement secondaire (T-013) : elle garde sa capacité et ses
 * inscriptions, et gagne une salle et des intervenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speakers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('name');
            $table->string('role')->nullable();
            $table->string('company')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
        });

        // Un intervenant peut parler dans plusieurs sessions, une session
        // réunir plusieurs intervenants.
        Schema::create('session_speakers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained('speakers')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['speaker_id', 'event_id']);
            $table->index(['organization_id', 'event_id']);
        });

        Schema::table('events', function (Blueprint $table): void {
            // Salle d'une session : sert au programme et au repérage des
            // chevauchements dans une même salle.
            $table->string('room')->nullable()->after('online_url');
        });

        OrganizationRowLevelSecurity::enable('speakers');
        OrganizationRowLevelSecurity::enable('session_speakers');
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('room');
        });

        OrganizationRowLevelSecurity::disable('session_speakers');
        OrganizationRowLevelSecurity::disable('speakers');
        Schema::dropIfExists('session_speakers');
        Schema::dropIfExists('speakers');
    }
};
