<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Actions\SendWhatsapp;
use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\Log;

/**
 * Décide QUI a le droit de recevoir un message WhatsApp (Contact,
 * Domain/Contact) — même raisonnement que SendEmailToContact (T-043) :
 * Domain/Messaging ne peut pas porter cette décision sans dépendre d'un
 * autre module de Domain/ (section 3 du CLAUDE.md).
 */
final class SendWhatsappToContact
{
    public function __construct(
        private readonly ResolveWhatsappTemplateVariables $resolveWhatsappTemplateVariables,
        private readonly SendWhatsapp $sendWhatsapp,
    ) {}

    public function handle(Organization $organization, Contact $contact, WhatsappTemplate $template, ?Event $event): ?WhatsappMessage
    {
        if ($contact->phone_e164 === null) {
            return null;
        }

        if ($contact->isWhatsappSuppressed()) {
            Log::info("WhatsApp non envoyé : contact #{$contact->id} exclu des envois (consentement absent ou numéro invalide).");

            return null;
        }

        return $this->sendWhatsapp->handle(
            $organization,
            $contact->phone_e164,
            $contact->id,
            $template->id,
            $template->provider_template_sid,
            $this->resolveWhatsappTemplateVariables->handle($template, $contact, $event),
        );
    }
}
