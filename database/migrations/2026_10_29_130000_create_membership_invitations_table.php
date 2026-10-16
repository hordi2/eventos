<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invitation d'un membre à l'échelle de l'organisation (Owner excepté,
 * décision produit — le transfert de propriété n'est pas ce ticket), rôle
 * choisi à l'invitation, même mécanique de jeton que collaborators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Empreinte SHA-256 du jeton du lien, jamais le jeton lui-même :
            // une fuite de la base ne donne pas de lien d'invitation valable.
            $table->string('invitation_token_hash', 64)->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
        });

        DB::statement(
            'create unique index membership_invitations_token_hash_unique
                on membership_invitations (invitation_token_hash)
                where invitation_token_hash is not null'
        );

        // Une invitation acceptée garde sa ligne (journal) mais ne bloque
        // plus une nouvelle invitation à la même adresse (ex. : retirée de
        // l'organisation, puis réinvitée).
        DB::statement(
            'create unique index membership_invitations_organization_email_unique
                on membership_invitations (organization_id, email)
                where deleted_at is null and accepted_at is null'
        );

        OrganizationRowLevelSecurity::enable('membership_invitations');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('membership_invitations');
        Schema::dropIfExists('membership_invitations');
    }
};
