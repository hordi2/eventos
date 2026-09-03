<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrairement à email_templates (éditeur par blocs, T-044), un modèle
 * WhatsApp est approuvé par Meta hors de l'app (accord explicite) : cette
 * table ne stocke jamais le contenu du message, seulement la référence au
 * modèle déjà approuvé chez le prestataire et la correspondance entre nos
 * variables de fusion et ses {{1}}, {{2}}...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('name');
            // Content SID Twilio (ex. HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx)
            // du modèle déjà approuvé — jamais créé ni soumis par l'app.
            $table->string('provider_template_sid');
            $table->string('language', 10)->default('fr');
            // Informatif seulement (marketing/utility) : n'influence aucune
            // règle de l'app, sert de repère à l'organisateur.
            $table->string('category')->nullable();
            // Liste ordonnée ["first_name", "event_date", ...] : la position
            // dans le tableau correspond au numéro de variable Twilio
            // (index 0 => {{1}}, index 1 => {{2}}...).
            $table->jsonb('variable_mapping');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name']);
        });

        OrganizationRowLevelSecurity::enable('whatsapp_templates');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('whatsapp_templates');
        Schema::dropIfExists('whatsapp_templates');
    }
};
