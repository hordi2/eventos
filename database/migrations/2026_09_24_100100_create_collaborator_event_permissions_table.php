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
        Schema::create('collaborator_event_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaborator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // « Aucun accès » est une valeur (none), pas une ligne supprimée :
            // retirer l'accès à un événement ne fait jamais de DELETE
            // (section 4.5 du CLAUDE.md).
            $table->string('permission');
            $table->timestamps();

            $table->unique(['collaborator_id', 'event_id']);
            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('collaborator_event_permissions');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('collaborator_event_permissions');
        Schema::dropIfExists('collaborator_event_permissions');
    }
};
