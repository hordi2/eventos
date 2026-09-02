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
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('holder_type');
            $table->string('holder_id');

            $table->string('reservation_key')->unique();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('position');
            $table->string('status');
            $table->timestamp('promoted_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'holder_type', 'holder_id', 'status', 'position']);
        });

        OrganizationRowLevelSecurity::enable('waitlist_entries');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('waitlist_entries');
        Schema::dropIfExists('waitlist_entries');
    }
};
