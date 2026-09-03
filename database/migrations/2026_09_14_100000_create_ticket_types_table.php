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
        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_free')->default(false);
            $table->char('currency', 3);

            $table->unsignedInteger('min_per_order')->default(1);
            $table->unsignedInteger('max_per_order')->nullable();
            // Quota global tous paliers confondus. Null = illimité. Le
            // quota par palier (early bird...) vit séparément sur
            // ticket_price_tiers.quantity.
            $table->unsignedInteger('total_quantity')->nullable();

            $table->string('vat_mode')->default('none');
            // Taux en points de base (2000 = 20,00 %), jamais en float
            // (§4.2 CLAUDE.md).
            $table->unsignedInteger('vat_rate_bp')->default(0);

            // Pas de valeur par défaut, volontairement : M5.1 exige un choix
            // explicite affiché à l'acheteur, jamais une hypothèse silencieuse
            // côté organisateur.
            $table->boolean('fees_absorbed');

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('ticket_types');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('ticket_types');
        Schema::dropIfExists('ticket_types');
    }
};
