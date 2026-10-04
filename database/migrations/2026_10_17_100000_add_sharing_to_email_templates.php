<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles d'e-mail partagés par une agence avec ses comptes clients (D10) :
 * elle écrit sa lettre d'invitation une fois, chacun de ses clients la
 * reprend chez lui.
 *
 * Seuls les modèles d'e-mail se partagent : un modèle WhatsApp porte
 * l'identifiant du modèle validé chez le prestataire de l'agence, qui ne
 * vaut rien dans le compte d'un client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->boolean('is_shared_with_clients')->default(false)->after('blocks');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropColumn('is_shared_with_clients');
        });
    }
};
