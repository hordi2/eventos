<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentification multifactorielle par e-mail (Paramètres → Sécurité,
 * demande utilisateur) : un réglage personnel (`users.mfa_email_enabled`)
 * et une politique d'organisation forçant la MFA pour tous les membres
 * (`organizations.require_mfa_for_members`, réservée au propriétaire — voir
 * OrganizationPolicy::manageSecurity). Aucune table de code à usage unique :
 * le défi MFA en cours vit en session (voir AttemptLogin), pas en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('mfa_email_enabled')->default(false)->after('email_verified_at');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('require_mfa_for_members')->default(false)->after('theme_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('mfa_email_enabled');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('require_mfa_for_members');
        });
    }
};
