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
        Schema::create('registration_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            // form_field_id, jamais dupliqué (ni la clé, ni le type) : la
            // FormField pointée appartient à une FormVersion figée dès sa
            // publication (§4.7 du CLAUDE.md), donc cette référence reste
            // valable pour toujours, même si le formulaire est révisé depuis.
            $table->foreignId('form_field_id')->constrained()->restrictOnDelete();

            $table->jsonb('value')->nullable();

            $table->timestamps();

            $table->unique(['registration_id', 'form_field_id']);
            $table->index(['organization_id', 'registration_id']);
        });

        OrganizationRowLevelSecurity::enable('registration_answers');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('registration_answers');
        Schema::dropIfExists('registration_answers');
    }
};
