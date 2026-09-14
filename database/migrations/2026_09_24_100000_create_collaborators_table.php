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
        Schema::create('collaborators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');

            // Renseigné à l'acceptation seulement : une invitation peut viser
            // une adresse qui n'a encore aucun compte Itaza.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Empreinte SHA-256 du jeton du lien, jamais le jeton lui-même :
            // une fuite de la base ne donne pas de lien d'invitation valable.
            $table->string('invitation_token_hash', 64)->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
            $table->index('user_id');
        });

        DB::statement(
            'create unique index collaborators_invitation_token_hash_unique
                on collaborators (invitation_token_hash)
                where invitation_token_hash is not null'
        );

        // Même principe que memberships : une seule invitation active par
        // adresse et par organisation, mais une personne retirée peut être
        // réinvitée (l'ancienne ligne reste en suppression logique).
        DB::statement(
            'create unique index collaborators_organization_email_unique
                on collaborators (organization_id, email)
                where deleted_at is null'
        );

        OrganizationRowLevelSecurity::enable('collaborators');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('collaborators');
        Schema::dropIfExists('collaborators');
    }
};
