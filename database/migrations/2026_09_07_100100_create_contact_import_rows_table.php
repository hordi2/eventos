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
        Schema::create('contact_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('row_number');
            $table->jsonb('raw_data');
            $table->string('status');
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'contact_import_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('contact_import_rows');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('contact_import_rows');
        Schema::dropIfExists('contact_import_rows');
    }
};
