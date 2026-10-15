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
        Schema::create('push_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('push_notifications');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('push_notifications');
        Schema::dropIfExists('push_notifications');
    }
};
