<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Don promis dans le formulaire d'inscription (T-056, décision produit :
 * « paiement après l'inscription ») : la commande naît de l'inscription et
 * le reçu reprend les informations du donateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Simple colonne côté modèle, jamais une relation Eloquent :
            // Domain/Ticketing ne dépend d'aucun modèle de Domain/Form.
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['organization_id', 'registration_id']);
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->string('donor_name')->nullable();
            $table->string('donor_company')->nullable();
            $table->jsonb('donor_address')->nullable();
            $table->boolean('is_anonymous')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropColumn(['donor_name', 'donor_company', 'donor_address', 'is_anonymous']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'registration_id']);
            $table->dropConstrainedForeignId('registration_id');
        });
    }
};
