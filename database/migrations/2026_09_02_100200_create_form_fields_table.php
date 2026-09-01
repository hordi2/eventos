<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();

            // Identifiant stable d'un champ à travers les versions (un renommage
            // change "label", jamais "key") : c'est ce qui permet de dire que le
            // champ n°7 de la version 1 et le champ n°23 de la version 2 sont
            // "le même champ", pour le mappage CRM et l'export (M2.1 du CDC).
            $table->string('key');
            $table->string('type');
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('config')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'form_version_id']);
        });

        DB::statement(
            'create unique index form_fields_version_key_unique
                on form_fields (form_version_id, key)'
        );

        OrganizationRowLevelSecurity::enable('form_fields');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('form_fields');
        Schema::dropIfExists('form_fields');
    }
};
