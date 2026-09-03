<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "email_automations" (T-045) renommée : une automatisation peut désormais
 * cibler l'e-mail OU WhatsApp (accord explicite, choix par automatisation)
 * — garder le nom "email" aurait été trompeur. La politique RLS est liée
 * au nom de table, donc désactivée puis recréée sous le nouveau nom plutôt
 * que simplement renommée.
 */
return new class extends Migration
{
    public function up(): void
    {
        OrganizationRowLevelSecurity::disable('email_automations');
        Schema::rename('email_automations', 'message_automations');

        Schema::table('message_automations', function (Blueprint $table): void {
            $table->string('channel')->default('email')->after('type');
            $table->foreignId('whatsapp_template_id')->nullable()->after('email_template_id')->constrained()->restrictOnDelete();
        });

        // email_template_id devient optionnel : exactement un des deux
        // template_id est renseigné, selon channel.
        DB::statement('alter table message_automations alter column email_template_id drop not null');

        OrganizationRowLevelSecurity::enable('message_automations');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('message_automations');

        Schema::table('message_automations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('whatsapp_template_id');
            $table->dropColumn('channel');
        });

        DB::statement('alter table message_automations alter column email_template_id set not null');

        Schema::rename('message_automations', 'email_automations');
        OrganizationRowLevelSecurity::enable('email_automations');
    }
};
