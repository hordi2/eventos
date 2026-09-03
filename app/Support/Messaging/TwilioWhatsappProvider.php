<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use Twilio\Rest\Client as TwilioClient;

final class TwilioWhatsappProvider implements WhatsappProvider
{
    /**
     * @param  array<int, string>  $contentVariables
     */
    public function send(string $toPhoneE164, string $contentSid, array $contentVariables, string $statusCallbackUrl): string
    {
        $client = new TwilioClient(config('services.twilio.sid'), config('services.twilio.auth_token'));

        $message = $client->messages->create(
            "whatsapp:{$toPhoneE164}",
            [
                'from' => 'whatsapp:'.config('services.twilio.whatsapp_from'),
                'contentSid' => $contentSid,
                'contentVariables' => json_encode($contentVariables),
                'statusCallback' => $statusCallbackUrl,
            ],
        );

        return $message->sid;
    }
}
