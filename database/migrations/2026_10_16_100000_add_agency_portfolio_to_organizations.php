<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portefeuille d'une agence événementielle (D10). Une agence gère les comptes
 * de ses clients ; chacun reste une organisation à part entière, cloisonnée
 * comme les autres, et peut lui être rendue — le client garde alors ses
 * événements, ses contacts et son historique.
 *
 * nullOnDelete : une agence qui s'en va ne doit jamais emporter les comptes
 * de ses clients avec elle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('is_agency')->default(false);
            $table->foreignId('managed_by_organization_id')
                ->nullable()
                ->after('is_agency')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->timestamp('managed_since')->nullable()->after('managed_by_organization_id');

            $table->index('managed_by_organization_id', 'organizations_portfolio_index');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex('organizations_portfolio_index');
            $table->dropConstrainedForeignId('managed_by_organization_id');
            $table->dropColumn(['is_agency', 'managed_since']);
        });
    }
};
