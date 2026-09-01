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
        Schema::create('field_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained()->cascadeOnDelete();

            $table->string('value');
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('quota')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'form_field_id']);
        });

        DB::statement(
            'create unique index field_options_field_value_unique
                on field_options (form_field_id, value)'
        );

        OrganizationRowLevelSecurity::enable('field_options');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('field_options');
        Schema::dropIfExists('field_options');
    }
};
