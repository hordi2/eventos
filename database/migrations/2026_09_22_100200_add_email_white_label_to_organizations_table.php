<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres → Étiquetage blanc (demande utilisateur) : nom d'expéditeur et
 * adresse de réponse personnalisés sur les e-mails envoyés aux invités.
 * Volontairement limité à cela — une vérification de domaine complète
 * (SPF/DKIM) suppose de choisir un prestataire d'e-mail dédié (Mailgun,
 * Postmark...) et d'avoir ses identifiants, aucun n'est configuré
 * aujourd'hui (voir aussi le docblock de config/plans.php pour le même
 * raisonnement côté Stripe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('email_from_name')->nullable()->after('require_mfa_for_members');
            $table->string('email_reply_to')->nullable()->after('email_from_name');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['email_from_name', 'email_reply_to']);
        });
    }
};
