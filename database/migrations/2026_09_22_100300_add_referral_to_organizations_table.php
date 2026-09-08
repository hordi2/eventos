<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres → Refer-a-Friend (demande utilisateur) : chaque organisation a
 * un code de parrainage unique, généré à la création (RegisterUser).
 * `referred_by_organization_id` capture le parrain au moment de
 * l'inscription (jamais modifiable ensuite). `referral_rewarded_at` rend la
 * récompense idempotente (règle 4.4 du CLAUDE.md) : appliquée une seule
 * fois, au premier paiement confirmé du filleul (voir ApplyReferralReward).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('referral_code')->nullable()->unique()->after('email_reply_to');
            $table->foreignId('referred_by_organization_id')->nullable()->after('referral_code')
                ->constrained('organizations')->nullOnDelete();
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_by_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('referred_by_organization_id');
            $table->dropColumn(['referral_code', 'referral_rewarded_at']);
        });
    }
};
