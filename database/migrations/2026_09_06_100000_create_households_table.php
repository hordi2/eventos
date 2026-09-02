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
        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name']);
        });

        OrganizationRowLevelSecurity::enable('households');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('households');
        Schema::dropIfExists('households');
    }
};
