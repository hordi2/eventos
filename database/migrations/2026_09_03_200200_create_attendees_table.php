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
        Schema::create('attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'registration_id']);
        });

        OrganizationRowLevelSecurity::enable('attendees');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('attendees');
        Schema::dropIfExists('attendees');
    }
};
