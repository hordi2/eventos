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
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('name');

            // Ajoutée par une migration séparée une fois form_versions créée
            // (référence circulaire : forms.current_version_id -> form_versions.id
            // -> form_versions.form_id -> forms.id).
            $table->unsignedBigInteger('current_version_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('forms');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('forms');
        Schema::dropIfExists('forms');
    }
};
