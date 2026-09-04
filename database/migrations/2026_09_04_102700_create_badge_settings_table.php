<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un seul réglage par événement (T-064) : « éditeur simple » se limite au
 * logo pour le MVP — nom, QR et couleur par tag sont fixes, calculés à la
 * génération plutôt que configurés ici (voir GenerateBadgePdf).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id')->unique();
            $table->string('logo_path')->nullable();

            $table->timestamps();
        });

        OrganizationRowLevelSecurity::enable('badge_settings');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('badge_settings');
        Schema::dropIfExists('badge_settings');
    }
};
