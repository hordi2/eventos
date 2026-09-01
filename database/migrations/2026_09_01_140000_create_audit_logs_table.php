<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            // Nullable : certaines actions (connexion) sont journalisées avant
            // qu'un contexte d'organisation ne soit résolu.
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            $table->nullableMorphs('causer');
            $table->nullableMorphs('subject');

            $table->string('action');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index('action');
        });

        // Deuxième barrière au niveau base de données (même logique que la RLS
        // pour le cloisonnement multi-tenant) : aucune ligne de audit_logs ne
        // peut être modifiée ou supprimée, même par une requête SQL brute mal
        // écrite ou malveillante.
        DB::statement(<<<'SQL'
            create or replace function prevent_audit_log_mutation() returns trigger as $$
            begin
                raise exception 'Les entrées du journal d''audit sont immuables.';
            end;
            $$ language plpgsql
        SQL);

        DB::statement(<<<'SQL'
            create trigger audit_logs_immutable
                before update or delete on audit_logs
                for each row execute function prevent_audit_log_mutation()
        SQL);
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists audit_logs_immutable on audit_logs');
        DB::statement('drop function if exists prevent_audit_log_mutation');
        Schema::dropIfExists('audit_logs');
    }
};
