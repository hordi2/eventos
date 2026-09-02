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
        Schema::create('registration_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            // Photo complète (identité + réponses) au moment juste avant une
            // modification par l'invité (§4.7 / T-033 : « historisation de la
            // version précédente ») — jamais modifiée après coup.
            $table->jsonb('snapshot');

            $table->timestamp('created_at');

            $table->index(['organization_id', 'registration_id']);
        });

        OrganizationRowLevelSecurity::enable('registration_revisions');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('registration_revisions');
        Schema::dropIfExists('registration_revisions');
    }
};
