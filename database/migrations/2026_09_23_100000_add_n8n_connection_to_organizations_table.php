<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connexion sortante vers l'instance n8n de l'organisation (Paramètres →
 * Intégrations, demande utilisateur) : contrairement à Zapier, n8n expose
 * une API publique authentifiée par clé, ce qui permet de coller la clé ici
 * plutôt que de recopier des URL de webhook à la main.
 *
 * La clé est chiffrée au repos (cast `encrypted`, APP_KEY) : c'est un secret
 * d'un système tiers appartenant au client, au même titre qu'un jeton de
 * paiement — il ne doit jamais être lisible en clair dans une sauvegarde de
 * base (section 7 du CLAUDE.md). Colonne `text` parce qu'un chiffré Laravel
 * est nettement plus long que la clé d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('n8n_base_url')->nullable()->after('referral_rewarded_at');
            $table->text('n8n_api_key')->nullable()->after('n8n_base_url');
            $table->timestamp('n8n_connected_at')->nullable()->after('n8n_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['n8n_base_url', 'n8n_api_key', 'n8n_connected_at']);
        });
    }
};
