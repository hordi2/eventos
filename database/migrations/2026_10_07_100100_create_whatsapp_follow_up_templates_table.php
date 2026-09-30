<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages de suivi envoyés à l'invité par WhatsApp autant que par e-mail
 * (D1 : WhatsApp est un canal de premier rang). WhatsApp n'accepte qu'un
 * modèle approuvé : l'organisation désigne celui qui sert à chaque cas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            // e-mail (par défaut, comme avant), whatsapp, ou les deux.
            $table->string('follow_up_channel', 20)->default('email')->after('ga4_measurement_id');
        });

        Schema::create('whatsapp_follow_up_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->foreignId('whatsapp_template_id')->constrained('whatsapp_templates')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['organization_id', 'purpose']);
        });

        OrganizationRowLevelSecurity::enable('whatsapp_follow_up_templates');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('whatsapp_follow_up_templates');
        Schema::dropIfExists('whatsapp_follow_up_templates');

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('follow_up_channel');
        });
    }
};
