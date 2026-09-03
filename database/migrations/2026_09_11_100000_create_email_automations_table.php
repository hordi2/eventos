<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_automations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Pas de relation Eloquent vers Event : Domain/Messaging ne
            // dépend jamais des modèles de Domain/Event (section 3 du
            // CLAUDE.md) — même contrainte FK que registrations.event_id,
            // simple colonne côté modèle.
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('type');
            // Segment ciblé (valeurs de App\Support\Segments\EventSegment) ;
            // null = tous les contacts de l'organisation. Sans objet pour
            // "confirmation" (déclenchée par inscription, pas par segment).
            $table->string('segment')->nullable();
            // Null pour "confirmation" (déclenchement immédiat par
            // inscription, jamais une échéance fixe).
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id', 'type']);
            $table->index(['organization_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('email_automations');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('email_automations');
        Schema::dropIfExists('email_automations');
    }
};
