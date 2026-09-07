<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des vérifications de santé de la plateforme (T-076, §18.2).
 * Volontairement hors du cloisonnement multi-tenant (§4.1 CLAUDE.md) : ce
 * n'est pas une donnée d'organisation, c'est l'état de l'infrastructure
 * elle-même, affiché à tous sur la page de statut publique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_status_checks', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('checked_at')->index();
            $table->boolean('is_healthy');
            $table->jsonb('components');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_status_checks');
    }
};
