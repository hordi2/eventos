<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sondages en direct (D9) : question à choix unique, ouverte puis fermée
 * par l'organisateur. event_id porte l'événement principal ou une session
 * précise, jamais de relation Eloquent vers Domain/Event (section 3 du
 * CLAUDE.md) — même règle que SessionQuestion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('question', 280);
            $table->string('status', 20)->default('draft');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('event_polls');

        Schema::create('event_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_poll_id')->constrained('event_polls')->cascadeOnDelete();

            $table->string('label', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('vote_count')->default(0);

            $table->timestamps();

            $table->index('event_poll_id');
        });

        OrganizationRowLevelSecurity::enable('event_poll_options');

        Schema::create('event_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_poll_id')->constrained('event_polls')->cascadeOnDelete();
            $table->foreignId('event_poll_option_id')->constrained('event_poll_options')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['event_poll_id', 'registration_id']);
            $table->index('organization_id');
        });

        OrganizationRowLevelSecurity::enable('event_poll_votes');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('event_poll_votes');
        Schema::dropIfExists('event_poll_votes');
        OrganizationRowLevelSecurity::disable('event_poll_options');
        Schema::dropIfExists('event_poll_options');
        OrganizationRowLevelSecurity::disable('event_polls');
        Schema::dropIfExists('event_polls');
    }
};
