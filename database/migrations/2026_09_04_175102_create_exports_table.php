<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Export CSV en tâche de fond (T-071) : une ligne par demande, ProcessExportJob
 * la fait progresser de pending à completed/failed. `columns` retient les clés
 * sélectionnées par l'organisateur, `segment` le filtre optionnel (uniquement
 * pertinent pour le type "contacts", voir BuildExportRows). `expires_at` fixé
 * à la complétion (+24h, AC du ticket) : un lien de téléchargement expiré
 * renvoie 404 sans qu'un job de purge planifié soit nécessaire pour ce MVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('columns');
            $table->string('segment')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('exports');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('exports');
        Schema::dropIfExists('exports');
    }
};
