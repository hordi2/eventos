<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions au conférencier (D9) : event_id porte soit l'événement
 * principal (pas de sous-événements), soit une session précise — comme
 * Registration vis-à-vis d'Event, jamais de distinction en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();

            $table->text('body');
            $table->unsignedInteger('upvote_count')->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('session_questions');

        Schema::create('session_question_upvotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_question_id')->constrained('session_questions')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();

            $table->timestamp('created_at')->nullable();

            $table->unique(['session_question_id', 'registration_id']);
            $table->index('organization_id');
        });

        OrganizationRowLevelSecurity::enable('session_question_upvotes');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('session_question_upvotes');
        Schema::dropIfExists('session_question_upvotes');
        OrganizationRowLevelSecurity::disable('session_questions');
        Schema::dropIfExists('session_questions');
    }
};
