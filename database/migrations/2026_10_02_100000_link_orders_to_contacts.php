<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relie une commande de billets au contact de l'acheteur, pour que
 * l'effacement RGPD d'un contact atteigne aussi ses achats (règle 4.5).
 * Simple colonne côté modèle : Domain/Ticketing ne dépend pas de
 * Domain/Contact (section 3 du CLAUDE.md) ; le lien est posé par
 * LinkOrderToContact. Les commandes antérieures restent non reliées : un
 * rapprochement par e-mail serait trop fragile pour un effacement légal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->after('registration_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'contact_id']);
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
