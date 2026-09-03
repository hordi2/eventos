<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Actions\RecordEmailWebhookEvent;
use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Messaging\Models\EmailMessageStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Public, sans authentification utilisateur : protégé par Basic Auth
 * (recommandation officielle Postmark) plutôt que par la session — un
 * webhook ne porte jamais de session organisateur. La mise à jour du
 * contact sur bounce dur/plainte est volontairement ici, jamais dans
 * Domain/Messaging : seul le contrôleur est autorisé à traverser
 * Domain/Contact et Domain/Messaging (voir le docblock de Contact::class).
 */
final class PostmarkWebhookController extends Controller
{
    public function __invoke(Request $request, RecordEmailWebhookEvent $action): JsonResponse
    {
        $this->authorizeWebhook($request);

        $emailMessage = $action->handle($request->all());

        if ($emailMessage !== null) {
            $this->invalidateContactIfNeeded($emailMessage);
        }

        return response()->json(['ok' => true]);
    }

    private function authorizeWebhook(Request $request): void
    {
        $username = config('services.postmark.webhook_username');
        $password = config('services.postmark.webhook_password');

        if ($username === null || $password === null) {
            throw new HttpException(500, 'Identifiants du webhook Postmark non configurés.');
        }

        if ($request->getUser() !== $username || $request->getPassword() !== $password) {
            throw new HttpException(403, 'Identifiants du webhook invalides.');
        }
    }

    private function invalidateContactIfNeeded(EmailMessage $emailMessage): void
    {
        $isSuppressingEvent = $emailMessage->status === EmailMessageStatus::Complained
            || ($emailMessage->status === EmailMessageStatus::Bounced && $emailMessage->bounce_type === 'hard');

        if (! $isSuppressingEvent || $emailMessage->contact_id === null) {
            return;
        }

        // Contexte déjà positionné par RecordEmailWebhookEvent dès que
        // l'organisation a pu être résolue depuis l'e-mail journalisé.
        Contact::query()->whereKey($emailMessage->contact_id)->update([
            'email_invalid_at' => now(),
            'email_invalid_reason' => $emailMessage->status === EmailMessageStatus::Complained ? 'complaint' : 'hard_bounce',
        ]);
    }
}
