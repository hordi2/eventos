<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Messaging\Models\EmailMessageStatus;
use App\Domain\Organization\Models\Organization;
use App\Jobs\SendEmailMessageJob;

/**
 * Mécanique d'envoi pure (T-043) : ne sait rien d'un éventuel contact
 * suppressed/désabonné — c'est à l'appelant de ne pas invoquer cette action
 * pour un destinataire qui ne doit pas recevoir d'e-mail (voir
 * App\Support\Messaging\SendEmailToContact, qui porte cette décision côté
 * Contact sans que Domain/Messaging ait besoin de connaître ce modèle).
 */
final class SendEmail
{
    public function handle(
        Organization $organization,
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?int $contactId,
        bool $isTransactional,
        ?string $unsubscribeUrl,
    ): EmailMessage {
        $emailMessage = EmailMessage::query()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contactId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'is_transactional' => $isTransactional,
            'status' => EmailMessageStatus::Queued,
        ]);

        SendEmailMessageJob::dispatch($emailMessage->id, $organization->id, $bodyHtml, $unsubscribeUrl);

        return $emailMessage;
    }
}
