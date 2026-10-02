<?php

declare(strict_types=1);

use App\Support\Antivirus\FileScanStatus;
use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les fichiers audio et vidéo déposés dans Itaza pour l'invitation — le mot
 * d'accueil des mariés, d'abord. Rangés hors du dossier public et servis par
 * un jeton non devinable, une fois l'analyse antivirus passée (§7 du
 * CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');

            // L'adresse publique du fichier : un identifiant séquentiel
            // laisserait deviner les médias des autres événements.
            $table->uuid('token')->unique();

            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            $table->string('scan_status', 20)->default(FileScanStatus::Pending->value);
            $table->timestamp('scanned_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('page_media');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('page_media');
        Schema::dropIfExists('page_media');
    }
};
