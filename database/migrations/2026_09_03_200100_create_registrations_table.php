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
        Schema::create('registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // event_id : simple colonne, pas de relation Eloquent — Domain/Form
            // ne dépend jamais d'un modèle de Domain/Event (section 3 du
            // CLAUDE.md), même règle déjà appliquée à forms.event_id.
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained()->restrictOnDelete();

            $table->string('status');
            $table->string('reservation_key')->unique();

            // Identité de l'invité : en attendant Contact (T-040), portée
            // directement ici plutôt que déduite d'un champ du formulaire —
            // voir la décision prise pour ce ticket.
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_e164')->nullable();

            $table->string('source')->nullable();
            $table->jsonb('utm')->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('locale')->nullable();

            $table->timestamp('registered_at');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id', 'status']);
            $table->index(['organization_id', 'event_id', 'email']);
        });

        OrganizationRowLevelSecurity::enable('registrations');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('registrations');
        Schema::dropIfExists('registrations');
    }
};
