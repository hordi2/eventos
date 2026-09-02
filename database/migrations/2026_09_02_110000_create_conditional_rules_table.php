<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditional_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_field_id')->constrained('form_fields')->cascadeOnDelete();

            $table->string('action');
            $table->json('condition_group');

            $table->timestamps();

            $table->index(['organization_id', 'form_version_id']);
        });

        // Au plus une règle par champ cible et par version : évite une
        // ambiguïté de précédence non spécifiée par le cahier des charges
        // (que faire si deux règles "afficher" et "masquer" ciblent le même
        // champ avec des conditions contradictoires ?).
        DB::statement(
            'create unique index conditional_rules_version_target_unique
                on conditional_rules (form_version_id, target_field_id)'
        );

        OrganizationRowLevelSecurity::enable('conditional_rules');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('conditional_rules');
        Schema::dropIfExists('conditional_rules');
    }
};
