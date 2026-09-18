<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Accord d'envoi : l'organisation s'engage à n'inviter que des personnes qui
 * l'ont accepté, sans liste achetée ni louée, dans le respect des lois
 * anti-spam. Demandé une fois, à l'ouverture de la liste d'invités ; une
 * seconde acceptation ne change ni la date ni son auteur.
 */
final class AcceptSenderAgreement
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Organization $organization, User $user): Organization
    {
        Gate::forUser($user)->authorize('updateGuests', $organization);

        if ($organization->sender_agreement_accepted_at !== null) {
            return $organization;
        }

        $organization->update([
            'sender_agreement_accepted_at' => CarbonImmutable::now(),
            'sender_agreement_accepted_by' => $user->id,
        ]);

        $this->recordAuditLog->handle(
            action: 'organization.sender_agreement_accepted',
            causer: $user,
            subject: $organization,
        );

        return $organization->refresh();
    }
}
