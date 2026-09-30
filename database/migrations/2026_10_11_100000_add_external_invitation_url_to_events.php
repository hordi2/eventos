<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitation hébergée ailleurs : l'organisateur a fait faire son site par
 * un prestataire, ou l'a produit avec un autre outil. Le lien personnel de
 * chaque invité mène alors à ce site, qui reçoit de quoi afficher le code
 * QR de l'invité et son bouton de réponse — Itaza s'adapte au site, et non
 * l'inverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('external_invitation_url', 2048)->nullable()->after('speaker_briefing');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('external_invitation_url');
        });
    }
};
