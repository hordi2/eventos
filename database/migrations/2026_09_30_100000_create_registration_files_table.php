<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fichiers joints envoyés par les invités (bloc « Fichier joint », S-06 du
 * CDC) : rangés hors du dossier public, analysés par ClamAV avant d'être
 * servis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Simple colonne côté modèle : Domain/Form ne dépend pas de Domain/Event.
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Nulle si la question d'un brouillon de formulaire est réécrite :
            // la ligne reste, pour que la purge retrouve le fichier sur le disque.
            $table->foreignId('form_field_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registration_draft_id')->nullable()->constrained()->nullOnDelete();
            // Nulle tant que l'inscription n'est pas confirmée.
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();

            // Référence gardée dans la réponse et le formulaire, jamais l'identifiant.
            // En texte plutôt qu'en uuid : une saisie invalide ne doit pas faire
            // échouer la requête SQL.
            $table->string('token', 36)->unique();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            $table->string('scan_status')->default('pending');
            $table->string('scan_signature')->nullable();
            $table->timestamp('scanned_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id', 'scan_status']);
            $table->index(['organization_id', 'registration_id']);
        });

        OrganizationRowLevelSecurity::enable('registration_files');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('registration_files');
        Schema::dropIfExists('registration_files');
    }
};
