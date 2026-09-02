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
        Schema::create('registration_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // event_id / form_version_id : simples colonnes, pas de relation
            // Eloquent vers Domain/Event (section 3 du CLAUDE.md).
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained()->restrictOnDelete();

            $table->string('resume_token')->unique();
            $table->jsonb('identity')->nullable();
            $table->jsonb('answers')->nullable();
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('registration_drafts');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('registration_drafts');
        Schema::dropIfExists('registration_drafts');
    }
};
