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
        // Simple table pivot : pas de deleted_at (§4.5 du CLAUDE.md vise les
        // enregistrements métier, pas une relation détachable/rattachable à
        // volonté — même choix que les lignes de contact_import_rows).
        // organization_id est tout de même nécessaire ici, dupliqué de
        // contact_id/tag_id : la RLS s'applique par table, pas par jointure.
        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['contact_id', 'tag_id']);
            $table->index(['organization_id', 'tag_id']);
        });

        OrganizationRowLevelSecurity::enable('contact_tag');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('contact_tag');
        Schema::dropIfExists('contact_tag');
    }
};
