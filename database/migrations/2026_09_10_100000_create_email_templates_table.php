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
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('name');
            $table->string('subject');
            // Liste ordonnée de blocs ({type, ...champs propres au type}) —
            // voir App\Support\Messaging\RenderEmailTemplate pour les types
            // reconnus (heading, text, image, button, divider, spacer).
            $table->jsonb('blocks');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name']);
        });

        OrganizationRowLevelSecurity::enable('email_templates');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('email_templates');
        Schema::dropIfExists('email_templates');
    }
};
