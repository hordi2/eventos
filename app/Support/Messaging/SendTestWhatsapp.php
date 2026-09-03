<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Actions\SendWhatsapp;
use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;

/**
 * Un envoi de test part toujours par SendWhatsapp directement (jamais
 * SendWhatsappToContact) : il ne s'adresse pas réellement au contact
 * choisi pour les données de fusion, seulement à un numéro de test — même
 * raisonnement que SendTestEmail (T-044).
 */
final class SendTestWhatsapp
{
    public function __construct(
        private readonly ResolveWhatsappTemplateVariables $resolveWhatsappTemplateVariables,
        private readonly SendWhatsapp $sendWhatsapp,
    ) {}

    public function handle(Organization $organization, WhatsappTemplate $template, Contact $mergeContact, ?Event $event, string $toPhoneE164): WhatsappMessage
    {
        return $this->sendWhatsapp->handle(
            $organization,
            $toPhoneE164,
            null,
            $template->id,
            $template->provider_template_sid,
            $this->resolveWhatsappTemplateVariables->handle($template, $mergeContact, $event),
        );
    }
}
