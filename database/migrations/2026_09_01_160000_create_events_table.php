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
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('slug');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('status')->default('draft');

            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('timezone');

            $table->boolean('is_online')->default(false);
            $table->string('online_url')->nullable();
            $table->unsignedInteger('capacity')->nullable();

            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->string('access_mode')->default('public');
            $table->string('password_hash')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('allow_waitlist')->default(false);
            $table->boolean('allow_guest_edit')->default(false);
            $table->timestamp('edit_deadline')->nullable();

            $table->char('currency', 3)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        // Slug unique par organisation (pas globalement), en excluant les
        // événements supprimés pour permettre la réutilisation d'un slug.
        DB::statement(
            'create unique index events_organization_slug_unique
                on events (organization_id, slug)
                where deleted_at is null'
        );

        OrganizationRowLevelSecurity::enable('events');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('events');
        Schema::dropIfExists('events');
    }
};
