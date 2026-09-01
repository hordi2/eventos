<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'form_id']);
        });

        DB::statement(
            'create unique index form_versions_form_version_number_unique
                on form_versions (form_id, version_number)
                where deleted_at is null'
        );

        OrganizationRowLevelSecurity::enable('form_versions');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('form_versions');
        Schema::dropIfExists('form_versions');
    }
};
