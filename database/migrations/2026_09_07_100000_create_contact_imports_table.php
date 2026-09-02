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
        Schema::create('contact_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('original_filename');
            $table->string('file_path');
            $table->jsonb('headers');
            $table->jsonb('column_mapping')->nullable();
            $table->string('duplicate_strategy')->nullable();
            $table->string('status');

            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('contact_imports');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('contact_imports');
        Schema::dropIfExists('contact_imports');
    }
};
