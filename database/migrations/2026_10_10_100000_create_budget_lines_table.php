<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi budgétaire d'un événement (D7) : les postes de dépense et les
 * recettes attendues hors billetterie (sponsors, subventions), chacun avec
 * son prévu et son réalisé. Ce que rapporte la billetterie n'est pas
 * ressaisi ici : il est lu dans les commandes payées.
 *
 * Montants en entiers, plus petite unité monétaire, avec leur devise
 * (règle 4.2) — jamais de float ni de decimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('kind');
            $table->string('category');
            $table->string('label');
            $table->string('supplier')->nullable();
            $table->text('note')->nullable();

            $table->bigInteger('planned_amount_minor');
            $table->char('planned_currency', 3);
            // Nul tant que la dépense n'est pas engagée : « pas encore
            // réalisé » et « réalisé à zéro » ne se disent pas pareil.
            $table->bigInteger('actual_amount_minor')->nullable();
            $table->char('actual_currency', 3)->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
            $table->index(['event_id', 'kind']);
        });

        OrganizationRowLevelSecurity::enable('budget_lines');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('budget_lines');
        Schema::dropIfExists('budget_lines');
    }
};
