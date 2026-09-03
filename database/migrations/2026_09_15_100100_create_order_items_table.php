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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // ticket_type_id / price_tier_id sont de vraies relations : même
            // module (Domain/Ticketing), aucune règle de cloisonnement à
            // respecter ici.
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            // Null pour un billet gratuit (aucun palier de tarification).
            $table->foreignId('price_tier_id')->nullable()->constrained('ticket_price_tiers')->restrictOnDelete();

            // Nom figé au moment de l'achat ("Billet standard — Early bird") :
            // une commande passée ne doit jamais changer de libellé si le
            // type de billet ou le palier est renommé ensuite (même principe
            // que le versionnement de formulaire, §4.7 CLAUDE.md).
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_amount_minor');
            $table->char('unit_currency', 3);

            $table->timestamps();

            $table->index(['organization_id', 'order_id']);
        });

        OrganizationRowLevelSecurity::enable('order_items');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('order_items');
        Schema::dropIfExists('order_items');
    }
};
