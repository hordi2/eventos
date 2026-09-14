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
        Schema::create('event_checklist_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();

            // Décocher une étape remet marked_at à null plutôt que de supprimer
            // la ligne (section 4.5 du CLAUDE.md).
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'step']);
            $table->index('organization_id');
        });

        OrganizationRowLevelSecurity::enable('event_checklist_marks');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('event_checklist_marks');
        Schema::dropIfExists('event_checklist_marks');
    }
};
