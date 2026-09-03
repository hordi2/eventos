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
        Schema::create('ticket_price_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);

            // Null = illimité pour ce palier (contraint uniquement par le
            // quota global de ticket_types.total_quantity, s'il existe).
            $table->unsignedInteger('quantity')->nullable();

            // Fenêtre de validité du palier (early bird -> normal -> tardif).
            // Null des deux côtés = palier toujours dans sa fenêtre de dates,
            // seul le quota peut le clore.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'ticket_type_id']);
        });

        OrganizationRowLevelSecurity::enable('ticket_price_tiers');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('ticket_price_tiers');
        Schema::dropIfExists('ticket_price_tiers');
    }
};
