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
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('color');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name']);
        });

        OrganizationRowLevelSecurity::enable('tags');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('tags');
        Schema::dropIfExists('tags');
    }
};
