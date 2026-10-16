<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Support\Messaging\RecordWhatsappRsvpReply;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twilio\Security\RequestValidator;
use Twilio\TwiML\MessagingResponse;

/**
 * « Quand un message arrive » côté Twilio (D1) : une seule URL pour tout le
 * numéro WhatsApp de la plateforme, réglée une fois dans la console Twilio —
 * à la différence du rappel de statut (TwilioWhatsappWebhookController),
 * qui porte l'organisation dans son URL propre à chaque envoi. Protégé par
 * la même signature X-Twilio-Signature, jamais par CSRF (webhooks/* exclu
 * dans bootstrap/app.php).
 *
 * Ne traite que le clic d'un bouton de réponse rapide (ButtonPayload) :
 * un message texte libre est accusé réception sans action, en toute
 * transparence avec la règle « jamais de contenu libre généré » (§9 du
 * CLAUDE.md) — aucune conversation n'est menée ici.
 */
final class TwilioWhatsappInboundController extends Controller
{
    public function __invoke(Request $request, RecordWhatsappRsvpReply $recordWhatsappRsvpReply): Response
    {
        $this->authorizeWebhook($request);

        $messageSid = $request->string('MessageSid')->toString();
        $from = $request->string('From')->toString();
        $buttonPayload = $request->string('ButtonPayload')->toString();

        if ($messageSid !== '' && $from !== '' && $buttonPayload !== '') {
            $recordWhatsappRsvpReply->handle($messageSid, $from, $buttonPayload);
        }

        return response((string) new MessagingResponse, 200, ['Content-Type' => 'text/xml']);
    }

    private function authorizeWebhook(Request $request): void
    {
        $authToken = config('services.twilio.auth_token');

        if ($authToken === null) {
            throw new HttpException(500, 'Jeton d\'authentification Twilio non configuré.');
        }

        $signature = $request->header('X-Twilio-Signature', '');
        $validator = new RequestValidator($authToken);

        if (! $validator->validate($signature, $request->fullUrl(), $request->all())) {
            throw new HttpException(403, 'Signature Twilio invalide.');
        }
    }
}
