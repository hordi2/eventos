<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Actions\SendEmail;
use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Décide QUI a le droit de recevoir un e-mail (Contact, Domain/Contact) et
 * fabrique le lien de désabonnement — deux responsabilités que
 * Domain/Messaging ne peut pas porter lui-même sans dépendre d'un autre
 * module de Domain/ (section 3 du CLAUDE.md). Vit hors des deux modules,
 * comme App\Support\Segments\ComputeEventSegmentContacts pour la même
 * raison.
 */
final class SendEmailToContact
{
    public function __construct(
        private readonly SendEmail $sendEmail,
    ) {}

    public function handle(
        Organization $organization,
        Contact $contact,
        string $subject,
        string $bodyHtml,
        bool $isTransactional = true,
    ): ?EmailMessage {
        if ($contact->email === null) {
            return null;
        }

        if ($contact->isEmailSuppressed()) {
            Log::info("E-mail non envoyé : contact #{$contact->id} exclu des envois (désabonné ou invalide).");

            return null;
        }

        $unsubscribeUrl = $isTransactional
            ? null
            : URL::signedRoute('unsubscribe.show', ['organization' => $organization->id, 'contact' => $contact->id]);

        return $this->sendEmail->handle(
            $organization,
            $contact->email,
            $subject,
            $bodyHtml,
            $contact->id,
            $isTransactional,
            $unsubscribeUrl,
        );
    }
}
