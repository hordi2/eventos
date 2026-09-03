<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Actions\RecordWhatsappWebhookEvent;
use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappMessageStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twilio\Security\RequestValidator;

/**
 * Public, sans authentification utilisateur : protégé par la signature
 * X-Twilio-Signature (mécanisme officiel Twilio, différent de la Basic
 * Auth utilisée par Postmark en T-043 — chaque prestataire a sa propre
 * convention). La mise à jour du contact sur échec est ici, jamais dans
 * Domain/Messaging : seul le contrôleur est autorisé à traverser
 * Domain/Contact et Domain/Messaging (voir le docblock de Contact::class).
 */
final class TwilioWhatsappWebhookController extends Controller
{
    public function __invoke(Request $request, RecordWhatsappWebhookEvent $action): JsonResponse
    {
        $this->authorizeWebhook($request);

        $whatsappMessage = $action->handle($request->all());

        if ($whatsappMessage !== null) {
            $this->invalidateContactIfNeeded($whatsappMessage);
        }

        return response()->json(['ok' => true]);
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

    private function invalidateContactIfNeeded(WhatsappMessage $whatsappMessage): void
    {
        $isFailure = in_array($whatsappMessage->status, [WhatsappMessageStatus::Failed, WhatsappMessageStatus::Undelivered], true);

        if (! $isFailure || $whatsappMessage->contact_id === null) {
            return;
        }

        // Contexte déjà positionné par RecordWhatsappWebhookEvent dès que
        // l'organisation a pu être résolue depuis le message journalisé.
        Contact::query()->whereKey($whatsappMessage->contact_id)->update([
            'whatsapp_invalid_at' => now(),
            'whatsapp_invalid_reason' => $whatsappMessage->failed_reason ?? 'failed',
        ]);
    }
}
