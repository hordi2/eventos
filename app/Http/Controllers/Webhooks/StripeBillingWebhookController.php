<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Organization\Actions\RecordStripeBillingWebhookEvent;
use App\Http\Controllers\Controller;
use App\Support\Payments\InvalidWebhookSignatureException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Public, sans authentification utilisateur : protégé par la signature
 * Stripe-Signature, jamais par CSRF — même raisonnement que
 * StripeWebhookController (T-052). Endpoint distinct pour les événements
 * d'abonnement (T-074) : voir le docblock de RecordStripeBillingWebhookEvent.
 */
final class StripeBillingWebhookController extends Controller
{
    public function __invoke(Request $request, RecordStripeBillingWebhookEvent $action): JsonResponse
    {
        try {
            $action->handle($request->getContent(), $request->header('Stripe-Signature', ''));
        } catch (InvalidWebhookSignatureException $exception) {
            throw new HttpException(403, $exception->getMessage());
        }

        return response()->json(['ok' => true]);
    }
}
