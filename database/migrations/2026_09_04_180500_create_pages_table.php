<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contenu de la page événement publique (T-072, M6.1) : bannière, programme
 * et FAQ en JSON (simples listes ordonnées affichées telles quelles, aucun
 * CRUD indépendant par élément ni référence externe ne justifie des tables
 * séparées). Une ligne par événement (unique sur event_id) — un événement
 * sans ligne ici affiche la page avec les seuls champs déjà sur Event
 * (titre, description) et des blocs programme/FAQ vides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->string('banner_path')->nullable();
            $table->string('meta_description')->nullable();
            $table->json('program_items')->nullable();
            $table->json('faq_items')->nullable();

            $table->timestamps();

            $table->unique('event_id');
            $table->index('organization_id');
        });

        OrganizationRowLevelSecurity::enable('pages');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('pages');
        Schema::dropIfExists('pages');
    }
};
