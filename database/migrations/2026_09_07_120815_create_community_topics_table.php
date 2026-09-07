<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forum communautaire (page Paramètres/pied de page → Communauté) :
 * volontairement hors cloisonnement multi-tenant (§4.1 CLAUDE.md, comme
 * `system_status_checks` de T-076) — c'est un espace partagé entre TOUTES
 * les organisations utilisant Itaza, pas une donnée d'une organisation en
 * particulier. Aucune colonne organization_id, donc aucune exigence du
 * trait BelongsToOrganization ni de RLS (voir
 * DomainModelsRespectMultiTenancyTest, qui n'exempte que les modèles sans
 * cette colonne).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('category');
            $table->string('title');
            $table->text('body');
            $table->softDeletes();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_topics');
    }
};
