<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappMessageStatus;
use App\Domain\Organization\Models\Organization;
use App\Jobs\SendWhatsappMessageJob;

/**
 * Mécanique d'envoi pure, comme SendEmail (T-043) : ne sait rien d'un
 * éventuel contact désabonné/suppressed — voir
 * App\Support\Messaging\SendWhatsappToContact.
 */
final class SendWhatsapp
{
    /**
     * @param  array<int, string>  $contentVariables  {1: "valeur", 2: "valeur"...} — clés numériques (PHP les normalise ainsi), encodées en JSON par SendWhatsappMessageJob
     */
    public function handle(
        Organization $organization,
        string $toPhoneE164,
        ?int $contactId,
        int $whatsappTemplateId,
        string $providerTemplateSid,
        array $contentVariables,
    ): WhatsappMessage {
        $whatsappMessage = WhatsappMessage::query()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contactId,
            'whatsapp_template_id' => $whatsappTemplateId,
            'to_phone_e164' => $toPhoneE164,
            'status' => WhatsappMessageStatus::Queued,
        ]);

        SendWhatsappMessageJob::dispatch($whatsappMessage->id, $organization->id, $providerTemplateSid, $contentVariables);

        return $whatsappMessage;
    }
}
