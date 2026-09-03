<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Ticketing\Actions\RecordStripeWebhookEvent;
use App\Http\Controllers\Controller;
use App\Support\Payments\InvalidWebhookSignatureException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Public, sans authentification utilisateur : protégé par la signature
 * Stripe-Signature (mécanisme officiel Stripe), jamais par CSRF — un
 * webhook ne porte pas de session organisateur (exclusion dans
 * bootstrap/app.php, même règle que les webhooks Postmark/Twilio).
 */
final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, RecordStripeWebhookEvent $action): JsonResponse
    {
        try {
            $action->handle($request->getContent(), $request->header('Stripe-Signature', ''));
        } catch (InvalidWebhookSignatureException $exception) {
            throw new HttpException(403, $exception->getMessage());
        }

        return response()->json(['ok' => true]);
    }
}
