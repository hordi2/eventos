<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Ticketing\Actions\RecordFlutterwaveWebhookEvent;
use App\Http\Controllers\Controller;
use App\Support\Payments\InvalidWebhookSignatureException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Public, sans authentification utilisateur : protégé par la signature
 * flutterwave-signature (mécanisme officiel Flutterwave), jamais par CSRF
 * — même raisonnement que les webhooks Postmark/Twilio/Stripe existants.
 */
final class FlutterwaveWebhookController extends Controller
{
    public function __invoke(Request $request, RecordFlutterwaveWebhookEvent $action): JsonResponse
    {
        try {
            $action->handle($request->getContent(), $request->header('flutterwave-signature', ''));
        } catch (InvalidWebhookSignatureException $exception) {
            throw new HttpException(403, $exception->getMessage());
        }

        return response()->json(['ok' => true]);
    }
}
