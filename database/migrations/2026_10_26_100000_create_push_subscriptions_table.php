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
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            $table->text('endpoint');

            $table->timestamps();

            $table->unique('endpoint', 'push_subscriptions_endpoint_unique');
            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('push_subscriptions');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('push_subscriptions');
        Schema::dropIfExists('push_subscriptions');
    }
};
