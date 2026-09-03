<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dons (M5.6, T-056) : « don additionnel au moment du paiement du billet »
 * — un don est toujours rattaché à une commande, jamais autonome (le don
 * ponctuel hors achat de billet et le don récurrent sont explicitement
 * hors MVP, M5.6 : « (V2) »). Son montant est inclus dans orders.total et
 * suit donc le même cycle de paiement/remboursement que le reste de la
 * commande — aucun statut propre nécessaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            // Attribution à une cause/un projet spécifique (M5.6), en texte
            // libre : aucun module "causes" n'existe, hors périmètre MVP.
            $table->string('cause')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'order_id']);
        });

        OrganizationRowLevelSecurity::enable('donations');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('donations');
        Schema::dropIfExists('donations');
    }
};
