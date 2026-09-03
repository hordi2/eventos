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
        Schema::create('email_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();

            $table->string('to_email');
            $table->string('subject');
            $table->boolean('is_transactional')->default(true);
            $table->string('status');
            $table->string('provider')->default('postmark');
            // Unique mais nullable : deux lignes "en attente d'ID fournisseur"
            // (avant confirmation d'envoi) doivent pouvoir coexister sans
            // violer la contrainte — PostgreSQL n'applique jamais une
            // contrainte unique entre deux NULL.
            $table->string('provider_message_id')->nullable()->unique();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_type')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->text('failed_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'status']);
        });

        OrganizationRowLevelSecurity::enable('email_messages');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('email_messages');
        Schema::dropIfExists('email_messages');
    }
};
