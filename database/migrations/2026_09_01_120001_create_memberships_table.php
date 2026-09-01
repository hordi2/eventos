<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'user_id']);
        });

        // Unicité partielle : un même utilisateur ne peut avoir qu'une adhésion
        // active par organisation, mais peut être réinvité après un retrait
        // (adhésion précédente conservée en suppression logique).
        DB::statement(
            'create unique index memberships_organization_user_unique
                on memberships (organization_id, user_id)
                where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
