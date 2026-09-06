<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empêche de renvoyer la même alerte de quota plusieurs fois dans le même
 * mois (AC T-074 : « alerte à 80 % et 100 % ») : une ligne par
 * organisation/métrique/seuil/mois, l'unicité fait office de verrou
 * d'idempotence (même mécanique que stripe_webhook_events, §4.4 du CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->unsignedTinyInteger('threshold');
            $table->string('period');
            $table->timestamp('sent_at');

            $table->timestamps();

            $table->unique(['organization_id', 'metric', 'threshold', 'period']);
        });

        OrganizationRowLevelSecurity::enable('quota_alerts');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('quota_alerts');
        Schema::dropIfExists('quota_alerts');
    }
};
