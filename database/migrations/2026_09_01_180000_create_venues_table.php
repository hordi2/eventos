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
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('access_instructions')->nullable();
            $table->text('parking_info')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
        });

        OrganizationRowLevelSecurity::enable('venues');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('venues');
        Schema::dropIfExists('venues');
    }
};
