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
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained()->nullOnDelete();

            $table->string('to_phone_e164');
            $table->string('status');
            $table->string('provider')->default('twilio');
            // Unique mais nullable : même raison que email_messages
            // (provider_message_id) — deux lignes "en attente d'ID
            // fournisseur" doivent pouvoir coexister.
            $table->string('provider_message_id')->nullable()->unique();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failed_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('whatsapp_messages');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('whatsapp_messages');
        Schema::dropIfExists('whatsapp_messages');
    }
};
