<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan de table (M7.4, T-065). event_id reste une simple colonne (section 3
 * du CLAUDE.md) : Domain/CheckIn ne dépend jamais directement d'un modèle
 * de Domain/Event.
 *
 * position_x/position_y/width/height/rotation portent la mise en page de
 * l'éditeur visuel — en points flottants libres (pas de grille imposée),
 * l'éditeur gère lui-même l'échelle à l'affichage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('event_id');
            $table->string('name');
            $table->string('shape');
            $table->unsignedInteger('capacity');
            $table->float('position_x')->default(0);
            $table->float('position_y')->default(0);
            $table->float('width')->default(120);
            $table->float('height')->default(120);
            $table->float('rotation')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('seating_tables');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('seating_tables');
        Schema::dropIfExists('seating_tables');
    }
};
