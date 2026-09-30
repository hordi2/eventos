<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appel à contributions (D6) : une page publique où chacun propose un
 * sujet, puis l'évaluation et la sélection côté organisateur. L'appel a son
 * propre cycle de vie — ouvert, date limite, texte d'appel —, d'où une
 * table à part plutôt que trois colonnes de plus sur events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->boolean('is_open')->default(false);
            $table->text('intro')->nullable();
            $table->timestamp('closes_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Un seul appel par événement.
            $table->unique('event_id');
            $table->index(['organization_id', 'event_id']);
        });

        Schema::create('proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('proposal_call_id')->constrained('proposal_calls')->cascadeOnDelete();

            // Qui propose. Le proposant n'a pas de compte : ses coordonnées
            // vivent ici, et ne rejoignent la base contacts que s'il est
            // retenu et devient intervenant.
            $table->string('proposer_name');
            $table->string('proposer_email');
            $table->string('proposer_role')->nullable();
            $table->string('proposer_company')->nullable();
            $table->text('proposer_bio')->nullable();

            // Le sujet proposé.
            $table->string('title');
            $table->text('summary');
            $table->string('format');
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            // L'évaluation de l'organisateur.
            $table->string('status')->default('pending');
            // Deux notes bien distinctes : l'évaluation reste entre
            // organisateurs, le message part au proposant avec la décision.
            $table->text('review_note')->nullable();
            $table->text('decision_message')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            // Fiche intervenant créée à l'acceptation : elle empêche aussi
            // d'en créer deux si la décision est reprise.
            $table->foreignId('speaker_id')->nullable()->constrained('speakers')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('proposal_calls');
        OrganizationRowLevelSecurity::enable('proposals');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('proposals');
        OrganizationRowLevelSecurity::disable('proposal_calls');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('proposal_calls');
    }
};
