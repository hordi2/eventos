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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // event_id : simple colonne, pas de relation Eloquent — même
            // règle que forms.event_id / registrations.event_id (section 3
            // du CLAUDE.md).
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Identité de l'acheteur, avant tout Contact (T-040) : même
            // choix que registrations.email/first_name/last_name.
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone_e164')->nullable();

            $table->string('status')->default('pending');
            $table->string('reservation_key')->unique();

            $table->bigInteger('total_amount_minor');
            $table->char('total_currency', 3);

            // Non nul uniquement pendant "pending" : stock tenu 15 minutes
            // (M5.4), libéré par ExpireOrderJob à l'échéance.
            $table->timestamp('reserved_until')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            // Renseigné uniquement si la commande expire sans qu'aucun
            // paiement n'ait jamais été tenté (abandon de panier, distinct
            // d'un paiement tenté puis non abouti).
            $table->timestamp('abandoned_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('orders');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('orders');
        Schema::dropIfExists('orders');
    }
};
