<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Génération en masse des badges (T-064, AC : « 500 badges en queue ») :
 * une ligne par lancement, le job GenerateEventBadgesJob la fait progresser
 * de pending à completed/failed et y dépose le chemin du PDF final (planche
 * Avery, tous les badges d'un événement en un seul fichier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->string('status')->default('pending');
            $table->unsignedInteger('guest_count')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('badge_batches');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('badge_batches');
        Schema::dropIfExists('badge_batches');
    }
};
