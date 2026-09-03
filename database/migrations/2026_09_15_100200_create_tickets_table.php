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
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            // Dénormalisé depuis order_items pour interroger "tous les
            // billets d'un type" sans remonter par order_items à chaque
            // fois (ex. futur check-in, T-060).
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();

            $table->string('status')->default('valid');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'ticket_type_id']);
        });

        OrganizationRowLevelSecurity::enable('tickets');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('tickets');
        Schema::dropIfExists('tickets');
    }
};
