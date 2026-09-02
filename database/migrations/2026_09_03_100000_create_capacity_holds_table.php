<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacity_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // holder_type/holder_id identifient ce qui est capacité-limité
            // (événement, sous-événement, option de champ de formulaire...)
            // par une simple paire type+id plutôt qu'une relation Eloquent :
            // le moteur de capacité (Support/Capacity) reste ainsi utilisable
            // par n'importe quel module de Domain/ sans que ceux-ci aient à
            // se référencer entre eux (section 3 du CLAUDE.md).
            $table->string('holder_type');
            $table->string('holder_id');

            $table->string('reservation_key')->unique();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status');
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'holder_type', 'holder_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('capacity_holds');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('capacity_holds');
        Schema::dropIfExists('capacity_holds');
    }
};
