<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annuaire des participants (D8). L'organisateur l'ouvre événement par
 * événement ; chaque participant y figure seulement s'il l'a demandé.
 *
 * Le consentement est horodaté plutôt que booléen : le RGPD demande de
 * pouvoir dire quand il a été donné, et son retrait l'efface (§10 du CDC).
 * Rien d'autre que le nom et la ligne que le participant écrit lui-même
 * n'est publié — ni e-mail, ni téléphone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('has_attendee_directory')->default(false)->after('allow_guest_edit');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->timestamp('directory_consent_at')->nullable();
            $table->string('directory_headline', 120)->nullable();

            $table->index(['event_id', 'directory_consent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex(['event_id', 'directory_consent_at']);
            $table->dropColumn(['directory_consent_at', 'directory_headline']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('has_attendee_directory');
        });
    }
};
