<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Codes promo d'un événement (lot 2) : une réduction en pourcentage ou en
 * montant sur le total des billets d'un panier. Les dons ne sont jamais
 * remisés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Toujours enregistré en majuscules : l'acheteur le saisit comme il veut.
            $table->string('code', 40);
            $table->string('kind');
            // Pourcentage en points de base (1000 = 10,00 %), jamais en float (§4.2).
            $table->unsignedInteger('percent_bp')->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('amount_currency', 3)->nullable();

            // Null = sans limite d'utilisations, ou sans borne de date.
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
        });

        // Un même mot ne peut pas désigner deux codes en service sur un
        // événement ; un code supprimé libère son mot.
        DB::statement('CREATE UNIQUE INDEX promo_codes_event_code_unique ON promo_codes (event_id, code) WHERE deleted_at IS NULL');

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('promo_code_id')->nullable()->after('registration_id')->constrained('promo_codes')->nullOnDelete();
            $table->bigInteger('discount_amount_minor')->nullable()->after('total_currency');
            $table->char('discount_currency', 3)->nullable()->after('discount_amount_minor');
        });

        OrganizationRowLevelSecurity::enable('promo_codes');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['discount_amount_minor', 'discount_currency']);
        });

        OrganizationRowLevelSecurity::disable('promo_codes');
        Schema::dropIfExists('promo_codes');
    }
};
